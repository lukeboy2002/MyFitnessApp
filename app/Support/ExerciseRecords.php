<?php

namespace App\Support;

use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Models\CardioLog;
use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\SetLog;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\CarbonInterface;

/**
 * Persoonlijke records van een oefening, uit de afgeronde trainingen vóór een bepaalde training.
 * Zo tellen de sets van de huidige training niet mee en kun je zien of je er eentje verbetert.
 */
class ExerciseRecords
{
    public ?float $heaviestWeight = null;

    public ?int $heaviestReps = null;

    public ?float $bestOneRepMax = null;

    public ?int $mostReps = null;

    public ?int $longestSet = null;          // seconden (type tijd)

    public ?int $longestDistance = null;     // meters (cardio)

    public ?int $longestDuration = null;     // seconden (cardio)

    public ?int $fastestKm = null;           // seconden (tussentijden)

    public ?int $mostFloors = null;          // stairmaster

    public ?string $lastTime = null;         // korte samenvatting van de vorige keer

    /** @var array<int, SetLog> afgevinkte sets van de vorige keer, per setnummer (kracht, lichaamsgewicht, tijd) */
    public array $lastSets = [];

    public function __construct(public ExerciseType $type) {}

    /** @return array<int, self> records per exercise_id, voor alle oefeningen in deze training */
    public static function forSession(WorkoutSession $session): array
    {
        $session->loadMissing('exercises.exercise');

        return $session->exercises->unique('exercise_id')->mapWithKeys(fn (SessionExercise $item) => [
            $item->exercise_id => self::build($session, $item->exercise_id, $item->exercise->type),
        ])->all();
    }

    /** Records van één oefening, bv. om bij het afvinken te kijken of je er eentje verbetert */
    public static function build(WorkoutSession $session, int $exerciseId, ExerciseType $type): self
    {
        return self::from($session->user_id, $exerciseId, $type, $session->started_at);
    }

    /** Alle records van een oefening tot nu toe, bv. voor de Progress-pagina */
    public static function forUser(User $user, Exercise $exercise): self
    {
        return self::from($user->id, $exercise->id, $exercise->type);
    }

    private static function from(int $userId, int $exerciseId, ExerciseType $type, ?CarbonInterface $before = null): self
    {
        $records = new self($type);

        $history = SessionExercise::query()
            ->where('exercise_id', $exerciseId)
            ->whereHas('session', fn ($q) => $q->where('user_id', $userId)
                ->where('status', SessionStatus::Completed)
                ->when($before, fn ($q) => $q->where('started_at', '<', $before)))
            ->with(['session', 'sets' => fn ($q) => $q->where('is_completed', true), 'cardio.splits'])
            ->get()
            ->sortByDesc(fn (SessionExercise $item) => $item->session->started_at);

        foreach ($history as $item) {
            $type === ExerciseType::Cardio ? $records->addCardio($item->cardio) : $item->sets->each(fn (SetLog $set) => $records->addSet($set));
        }

        $last = $history->first(fn (SessionExercise $item) => $type === ExerciseType::Cardio
            ? $item->cardio?->duration_seconds || $item->cardio?->distance_m || $item->cardio?->floors
            : $item->sets->isNotEmpty());
        $records->lastTime = $last ? $records->summarize($last) : null;
        $records->lastSets = $last && $type !== ExerciseType::Cardio ? $last->sets->keyBy('set_number')->all() : [];

        return $records;
    }

    /** Een oefening uit een latere training meetellen (oudste eerst), bv. om records over de hele historie te tellen */
    public function add(SessionExercise $item): void
    {
        if ($this->type === ExerciseType::Cardio) {
            $this->addCardio($item->cardio);
        } else {
            $item->sets->each(fn (SetLog $set) => $this->addSet($set));
        }

        if ($this->type === ExerciseType::Cardio ? $item->cardio?->duration_seconds || $item->cardio?->distance_m || $item->cardio?->floors : $item->sets->isNotEmpty()) {
            $this->lastTime = $this->summarize($item);
        }
    }

    /** Hoeveel records deze oefening uit een training verbetert (afgevinkte sets of cardio-records) */
    public function countRecords(SessionExercise $item): int
    {
        return $this->type === ExerciseType::Cardio
            ? count($this->cardioRecords($item->cardio))
            : $item->sets->filter(fn (SetLog $set) => $this->isRecordSet($set))->count();
    }

    public function isEmpty(): bool
    {
        return $this->lastTime === null;
    }

    /** Geschatte 1RM (Epley): gewicht × (1 + reps / 30) */
    public static function oneRepMax(?float $weight, ?int $reps): ?float
    {
        if (! $weight || $weight <= 0 || ! $reps) {
            return null;
        }

        return round($reps === 1 ? $weight : $weight * (1 + $reps / 30), 1);
    }

    /** Verbetert deze (afgevinkte) set een record? Alleen als er al een vorige keer is. */
    public function isRecordSet(SetLog $set): bool
    {
        if ($this->isEmpty() || ! $set->is_completed) {
            return false;
        }

        $weight = filled($set->weight) ? (float) $set->weight : null;

        return match ($this->type) {
            ExerciseType::Strength => ($weight && $weight > ($this->heaviestWeight ?? 0))
                || (self::oneRepMax($weight, $set->reps) ?? 0) > ($this->bestOneRepMax ?? PHP_FLOAT_MAX),
            ExerciseType::Bodyweight => ($set->reps ?? 0) > ($this->mostReps ?? PHP_INT_MAX)
                || ($weight > 0 && $weight > ($this->heaviestWeight ?? 0)),
            ExerciseType::Duration => ($set->duration_seconds ?? 0) > ($this->longestSet ?? PHP_INT_MAX),
            default => false,
        };
    }

    /** Korte omschrijving van een set, bv. "42,5 kg × 10", "15 reps" of "60 sec" */
    public function describeSet(SetLog $set): string
    {
        $n = fn ($v) => WorkoutSession::formatNumber($v);
        $weight = filled($set->weight) && (float) $set->weight !== 0.0 ? (float) $set->weight : null;

        return match ($this->type) {
            ExerciseType::Strength => ($weight ? $n($weight).' kg × ' : '').$set->reps,
            ExerciseType::Bodyweight => $set->reps.' reps'.($weight ? ' · '.($weight > 0 ? '+' : '').$n($weight).' kg' : ''),
            default => $set->duration_seconds.' sec',
        };
    }

    /** Welke cardio-records deze log verbetert: lijst van labels */
    public function cardioRecords(?CardioLog $log): array
    {
        if ($this->isEmpty() || ! $log) {
            return [];
        }

        $fastest = $log->splits->pluck('duration_seconds')->filter()->min();

        return array_keys(array_filter([
            'Langste afstand' => $log->distance_m && $log->distance_m > ($this->longestDistance ?? PHP_INT_MAX),
            'Langste tijd' => $log->duration_seconds && $log->duration_seconds > ($this->longestDuration ?? PHP_INT_MAX),
            'Snelste km' => $fastest && $fastest < ($this->fastestKm ?? 0),
            'Meeste floors' => $log->floors && $log->floors > ($this->mostFloors ?? PHP_INT_MAX),
        ]));
    }

    /** De records als [label => waarde], voor op het scherm */
    public function list(): array
    {
        $n = fn ($v) => WorkoutSession::formatNumber($v);

        return array_filter(match ($this->type) {
            ExerciseType::Strength => [
                'Zwaarste' => $this->heaviestWeight ? $n($this->heaviestWeight).' kg × '.$this->heaviestReps : null,
                'Geschat 1RM' => $this->bestOneRepMax ? $n($this->bestOneRepMax).' kg' : null,
            ],
            ExerciseType::Bodyweight => [
                'Meeste reps' => $this->mostReps,
                'Zwaarste extra' => $this->heaviestWeight > 0 ? '+'.$n($this->heaviestWeight).' kg × '.$this->heaviestReps : null,
            ],
            ExerciseType::Duration => [
                'Langste set' => $this->longestSet ? $this->longestSet.' sec' : null,
            ],
            ExerciseType::Cardio => [
                'Langste afstand' => $this->longestDistance ? $n($this->longestDistance / 1000).' km' : null,
                'Langste tijd' => $this->longestDuration ? WorkoutSession::clock($this->longestDuration).' min' : null,
                'Snelste km' => $this->fastestKm ? CardioLog::pace($this->fastestKm) : null,
                'Meeste floors' => $this->mostFloors,
            ],
        } + ['Vorige keer' => $this->lastTime]);
    }

    private function addSet(SetLog $set): void
    {
        $weight = filled($set->weight) ? (float) $set->weight : null;

        if ($weight !== null && ($weight > ($this->heaviestWeight ?? PHP_FLOAT_MIN) || ($weight == $this->heaviestWeight && $set->reps > $this->heaviestReps))) {
            $this->heaviestWeight = $weight;
            $this->heaviestReps = $set->reps;
        }

        if (($oneRepMax = self::oneRepMax($weight, $set->reps)) && $oneRepMax > ($this->bestOneRepMax ?? 0)) {
            $this->bestOneRepMax = $oneRepMax;
        }

        $this->mostReps = max($this->mostReps ?? 0, $set->reps ?? 0) ?: null;
        $this->longestSet = max($this->longestSet ?? 0, $set->duration_seconds ?? 0) ?: null;
    }

    private function addCardio(?CardioLog $log): void
    {
        if (! $log) {
            return;
        }

        $this->longestDistance = max($this->longestDistance ?? 0, $log->distance_m ?? 0) ?: null;
        $this->longestDuration = max($this->longestDuration ?? 0, $log->duration_seconds ?? 0) ?: null;
        $this->mostFloors = max($this->mostFloors ?? 0, $log->floors ?? 0) ?: null;

        if ($fastest = $log->splits->pluck('duration_seconds')->filter()->min()) {
            $this->fastestKm = min($this->fastestKm ?? PHP_INT_MAX, $fastest);
        }
    }

    /** "3 × 10 · 40 kg", "12, 10, 8 reps" of "20:00 min · 5,2 km" */
    private function summarize(SessionExercise $item): string
    {
        $n = fn ($v) => WorkoutSession::formatNumber($v);

        if ($this->type === ExerciseType::Cardio) {
            return collect([
                $item->cardio->duration_seconds ? WorkoutSession::clock($item->cardio->duration_seconds).' min' : null,
                $item->cardio->distance_m ? $n($item->cardio->distance_m / 1000).' km' : null,
                $item->cardio->floors ? $item->cardio->floors.' floors' : null,
            ])->filter()->implode(' · ');
        }

        if ($this->type === ExerciseType::Duration) {
            return $item->sets->pluck('duration_seconds')->implode(', ').' sec';
        }

        $sets = $item->sets;
        $sameReps = $sets->pluck('reps')->unique()->count() === 1;
        $weights = $sets->pluck('weight')->filter(fn ($w) => filled($w) && (float) $w !== 0.0)->map(fn ($w) => (float) $w)->unique();

        return ($sameReps ? $sets->count().' × '.$sets->first()->reps : $sets->pluck('reps')->implode(', ').' reps')
            .($weights->count() === 1 ? ' · '.($this->type === ExerciseType::Bodyweight && $weights->first() > 0 ? '+' : '').$n($weights->first()).' kg' : '')
            .($weights->count() > 1 ? ' · '.$n($weights->min()).'–'.$n($weights->max()).' kg' : '');
    }
}
