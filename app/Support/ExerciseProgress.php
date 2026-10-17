<?php

namespace App\Support;

use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\SetLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Voortgang van één oefening: per keer dat je hem deed een rij voor de tabel, plus een reeks voor het grafiekje.
 */
class ExerciseProgress
{
    /** @var Collection<int, array> nieuwste eerst */
    public Collection $rows;

    public function __construct(public User $user, public Exercise $exercise)
    {
        $type = $exercise->type;

        $rows = SessionExercise::query()
            ->where('exercise_id', $exercise->id)
            ->whereHas('session', fn ($q) => $q->where('user_id', $user->id)->where('status', SessionStatus::Completed))
            ->with(['session', 'sets' => fn ($q) => $q->where('is_completed', true), 'cardio.splits'])
            ->get()
            ->sortBy(fn (SessionExercise $item) => $item->session->started_at)
            ->map(fn (SessionExercise $item) => $type === ExerciseType::Cardio ? $this->cardioRow($item) : $this->setsRow($item))
            ->filter()
            ->values();

        // Cardio: grafiek en ▲/▼ op afstand (km), of op tijd (min) als je nooit een afstand invult
        if ($type === ExerciseType::Cardio) {
            $byDistance = $rows->contains(fn ($row) => $row['distance_m']);
            $rows = $rows->map(fn ($row) => ['value' => $byDistance
                ? ($row['distance_m'] ? round($row['distance_m'] / 1000, 2) : null)
                : ($row['duration_seconds'] ? round($row['duration_seconds'] / 60, 1) : null)] + $row);
        }

        // Verschil met de vorige keer, op de hoofdwaarde van de rij
        $previous = null;
        $this->rows = $rows->map(function (array $row) use (&$previous) {
            $row['delta'] = $previous !== null && $row['value'] !== null ? round($row['value'] - $previous, 1) : null;
            $previous = $row['value'] ?? $previous;

            return $row;
        })->reverse()->values();
    }

    public static function for(User $user, Exercise $exercise): self
    {
        return new self($user, $exercise);
    }

    /** Naam en eenheid van de hoofdwaarde (grafiek en ▲/▼) */
    public function measure(): array
    {
        return match ($this->exercise->type) {
            ExerciseType::Strength => ['Geschat 1RM', 'kg'],
            ExerciseType::Bodyweight => ['Totaal reps', 'reps'],
            ExerciseType::Duration => ['Totale tijd', 'sec'],
            ExerciseType::Cardio => $this->rows->contains(fn ($row) => $row['distance_m'])
                ? ['Afstand', 'km']
                : ['Tijd', 'min'],
        };
    }

    /** @return Collection<int, array{date: CarbonInterface, value: float}> oudste eerst */
    public function points(): Collection
    {
        return $this->rows->reverse()->filter(fn ($row) => $row['value'] !== null)
            ->map(fn ($row) => ['date' => $row['session']->started_at, 'value' => (float) $row['value']])
            ->values();
    }

    private function setsRow(SessionExercise $item): ?array
    {
        $sets = $item->sets;
        if ($sets->isEmpty()) {
            return null;
        }

        $type = $this->exercise->type;
        $records = new ExerciseRecords($type);
        $weights = $sets->map(fn (SetLog $set) => (float) $set->weight)->filter();

        $row = [
            'session' => $item->session,
            'sets' => $sets->count(),
        ];

        return $row + match ($type) {
            ExerciseType::Strength => (function () use ($sets, $records) {
                $best = $sets->sortByDesc(fn (SetLog $set) => ExerciseRecords::oneRepMax((float) $set->weight, $set->reps) ?? 0)->first();
                $oneRepMax = ExerciseRecords::oneRepMax((float) $best->weight, $best->reps);

                return [
                    'best' => $records->describeSet($best),
                    'volume' => (float) $sets->sum(fn (SetLog $set) => (int) $set->reps * (float) $set->weight),
                    'one_rep_max' => $oneRepMax,
                    'value' => $oneRepMax,
                ];
            })(),
            ExerciseType::Bodyweight => [
                'reps' => (int) $sets->sum('reps'),
                'most_reps' => (int) $sets->max('reps'),
                'extra' => $weights->isNotEmpty() ? $weights->max() : null,
                'value' => (int) $sets->sum('reps'),
            ],
            default => [
                'total' => (int) $sets->sum('duration_seconds'),
                'longest' => (int) $sets->max('duration_seconds'),
                'value' => (int) $sets->sum('duration_seconds'),
            ],
        };
    }

    private function cardioRow(SessionExercise $item): ?array
    {
        $log = $item->cardio;
        if (! $log || (! $log->duration_seconds && ! $log->distance_m && ! $log->floors)) {
            return null;
        }

        return [
            'session' => $item->session,
            'log' => $log,
            'duration_seconds' => $log->duration_seconds,
            'distance_m' => $log->distance_m,
            'fastest_km' => $log->splits->pluck('duration_seconds')->filter()->min(),
        ];
    }
}
