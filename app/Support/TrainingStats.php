<?php

namespace App\Support;

use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Models\BodyPart;
use App\Models\MuscleGroup;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Totalen van de afgeronde trainingen van een gebruiker: per week (maandag t/m zondag) of per periode.
 * Gerekend in PHP, zodat het op MySQL en in de tests (SQLite) hetzelfde werkt.
 */
class TrainingStats
{
    /** Lichaamsdelen die je alleen ziet als je ze die week trainde */
    public const OPTIONAL_PARTS = ['full-body', 'neck'];

    public function __construct(public User $user) {}

    /** Afgeronde trainingen vanaf $from (en vóór $until) */
    public function sessions(?CarbonImmutable $from = null, ?CarbonImmutable $until = null): Builder
    {
        return WorkoutSession::query()
            ->where('user_id', $this->user->id)
            ->where('status', SessionStatus::Completed)
            ->when($from, fn (Builder $q) => $q->where('started_at', '>=', $from))
            ->when($until, fn (Builder $q) => $q->where('started_at', '<', $until));
    }

    /**
     * Trainingstijd per week, oudste eerst, inclusief weken zonder training.
     *
     * @return Collection<int, array{start: CarbonImmutable, seconds: int, count: int}>
     */
    public function weeks(int $count = 12): Collection
    {
        $first = CarbonImmutable::now()->startOfWeek()->subWeeks($count - 1);

        $byWeek = $this->sessions($first)->get(['started_at', 'duration_seconds'])
            ->groupBy(fn (WorkoutSession $session) => $session->started_at->toImmutable()->startOfWeek()->toDateString());

        return collect(range(0, $count - 1))->map(function (int $i) use ($first, $byWeek) {
            $start = $first->addWeeks($i);
            $sessions = $byWeek->get($start->toDateString(), collect());

            return ['start' => $start, 'seconds' => (int) $sessions->sum('duration_seconds'), 'count' => $sessions->count()];
        });
    }

    /**
     * Trainingstijd per maand, oudste eerst, inclusief maanden zonder training.
     *
     * @return Collection<int, array{start: CarbonImmutable, seconds: int, count: int}>
     */
    public function months(int $count = 12): Collection
    {
        $first = CarbonImmutable::now()->startOfMonth()->subMonths($count - 1);

        $byMonth = $this->sessions($first)->get(['started_at', 'duration_seconds'])
            ->groupBy(fn (WorkoutSession $session) => $session->started_at->format('Y-m'));

        return collect(range(0, $count - 1))->map(function (int $i) use ($first, $byMonth) {
            $start = $first->addMonths($i);
            $sessions = $byMonth->get($start->format('Y-m'), collect());

            return ['start' => $start, 'seconds' => (int) $sessions->sum('duration_seconds'), 'count' => $sessions->count()];
        });
    }

    /**
     * Totalen in een periode: tijd, trainingen, afgevinkte sets, volume (Σ reps × kg) en cardio-afstand.
     *
     * @return array{seconds: int, count: int, sets: int, volume: float, distance_m: int}
     */
    public function totals(CarbonImmutable $from, ?CarbonImmutable $until = null): array
    {
        $sessions = $this->sessions($from, $until)
            ->with(['exercises.exercise', 'exercises.sets' => fn ($q) => $q->where('is_completed', true), 'exercises.cardio'])
            ->get();

        $exercises = $sessions->flatMap->exercises;
        $sets = $exercises->flatMap->sets;

        return [
            'seconds' => (int) $sessions->sum('duration_seconds'),
            'count' => $sessions->count(),
            'sets' => $sets->count(),
            'volume' => (float) $exercises->filter(fn (SessionExercise $item) => $item->exercise->type === ExerciseType::Strength)
                ->flatMap->sets->sum(fn ($set) => (int) $set->reps * (float) $set->weight),
            'distance_m' => (int) $exercises->sum(fn (SessionExercise $item) => $item->cardio?->distance_m ?? 0),
        ];
    }

    /**
     * Afgevinkte sets (cardio: afgeronde oefeningen) per primaire spiergroep, meeste eerst.
     *
     * @return Collection<int, array{name: string, color: ?string, count: int}>
     */
    public function muscleGroups(CarbonImmutable $from, ?CarbonImmutable $until = null): Collection
    {
        $items = SessionExercise::query()
            ->whereIn('workout_session_id', $this->sessions($from, $until)->select('id'))
            ->with(['exercise.muscleGroups', 'sets' => fn ($q) => $q->where('is_completed', true)])
            ->get();

        return $items
            ->map(fn (SessionExercise $item) => [
                'group' => $item->exercise->primaryMuscleGroup(),
                'count' => $item->exercise->type->usesSets() ? $item->sets->count() : (int) ($item->completed_at !== null),
            ])
            ->filter(fn ($row) => $row['group'] && $row['count'])
            ->groupBy(fn ($row) => $row['group']->id)
            ->map(fn (Collection $rows) => [
                'name' => $rows->first()['group']->name,
                'color' => $rows->first()['group']->color,
                'count' => $rows->sum('count'),
            ])
            ->sortByDesc('count')
            ->values();
    }

    /**
     * Sets per lichaamsdeel (via de primaire spiergroep), zonder opwarmsets en zonder cardio.
     * Alle lichaamsdelen staan erin, ook met 0, zodat je ziet wat je overslaat;
     * Full Body en Neck alleen als je er sets voor had. Per lichaamsdeel de spiergroepen met sets.
     *
     * @return Collection<int, array{name: string, sets: int, groups: Collection<int, array{name: string, color: ?string, sets: int}>}>
     */
    public function bodyParts(CarbonImmutable $from, ?CarbonImmutable $until = null): Collection
    {
        $items = SessionExercise::query()
            ->whereIn('workout_session_id', $this->sessions($from, $until)->select('id'))
            ->with(['exercise.muscleGroups', 'sets' => fn ($q) => $q->where('is_completed', true)->where('is_warmup', false)])
            ->get();

        $perGroup = $items
            ->filter(fn (SessionExercise $item) => $item->exercise->type->usesSets() && $item->exercise->primaryMuscleGroup())
            ->groupBy(fn (SessionExercise $item) => $item->exercise->primaryMuscleGroup()->id)
            ->map(fn (Collection $rows) => $rows->sum(fn (SessionExercise $item) => $item->sets->count()))
            ->filter();

        return BodyPart::visibleTo($this->user)
            ->with(['muscleGroups' => fn ($q) => $q->visibleTo($this->user)->orderBy('sort_order')->orderBy('name')])
            ->orderByRaw('user_id is not null')->orderBy('sort_order')->orderBy('name')
            ->get()
            ->map(function (BodyPart $part) use ($perGroup) {
                $groups = $part->muscleGroups
                    ->map(fn (MuscleGroup $group) => ['name' => $group->name, 'color' => $group->color, 'sets' => (int) $perGroup->get($group->id, 0)])
                    ->filter(fn (array $row) => $row['sets'] > 0)
                    ->sortByDesc('sets')
                    ->values();

                return ['name' => $part->name, 'slug' => $part->slug, 'sets' => (int) $groups->sum('sets'), 'groups' => $groups];
            })
            ->reject(fn (array $row) => in_array($row['slug'], self::OPTIONAL_PARTS, true) && $row['sets'] === 0)
            ->values();
    }

    /** Meest recente afgeronde trainingen */
    public function recent(int $count = 5): Collection
    {
        return $this->sessions()->with('exercises')->latest('started_at')->limit($count)->get();
    }

    /** 4u 35m, 45m */
    public static function hours(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);

        if ($minutes < 60) {
            return $minutes.__('m (minutes, short)');
        }

        return intdiv($minutes, 60).__('h (hours, short)').($minutes % 60 ? ' '.($minutes % 60).__('m (minutes, short)') : '');
    }

    /** 16439 → "16.439" */
    public static function kilos(float $kilos): string
    {
        return number_format($kilos, 0, ',', '.');
    }
}
