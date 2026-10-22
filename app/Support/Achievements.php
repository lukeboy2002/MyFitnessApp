<?php

namespace App\Support;

use App\Enums\BadgeMetric;
use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Models\Badge;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Badges van een gebruiker, berekend uit al zijn afgeronde trainingen (oudste eerst).
 * De badges zelf (drempels) beheert een admin; behaalde niveaus worden niet opgeslagen. Zo tellen ook
 * geïmporteerde trainingen mee, en van elk niveau weten we in welke training het is gehaald.
 */
class Achievements
{
    /** @return Collection<int, BadgeProgress> per badge-id, in de volgorde van de admin */
    public static function for(User $user): Collection
    {
        $badges = Badge::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get()
            ->mapWithKeys(fn (Badge $badge) => [$badge->id => new BadgeProgress($badge)]);

        if ($badges->isEmpty()) {
            return $badges;
        }

        $sessions = WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where('status', SessionStatus::Completed)
            ->with(['exercises.exercise', 'exercises.sets' => fn ($q) => $q->where('is_completed', true), 'exercises.cardio.splits'])
            ->orderBy('started_at')->orderBy('id')
            ->get();

        $totals = array_fill_keys(array_column(BadgeMetric::cases(), 'value'), 0);
        $perExercise = [];  // metric => [exercise_id => waarde]
        $history = [];      // exercise_id => ExerciseRecords
        $perWeek = [];      // maandag => aantal trainingen
        $streak = 0;
        $lastFullWeek = null;

        foreach ($sessions as $session) {
            $start = $session->started_at->toImmutable();
            $values = [];

            // Records: vergelijken met alles vóór deze training, daarna pas meetellen
            $sessionVolume = $sessionDistance = 0;
            foreach ($session->exercises as $item) {
                $history[$item->exercise_id] ??= new ExerciseRecords($item->exercise->type);
                $totals['records'] += $history[$item->exercise_id]->countRecords($item);
                $sessionVolume += self::volume($item);
                $sessionDistance += (int) ($item->cardio?->distance_m ?? 0);
                self::addPerExercise($perExercise, $item);
            }
            $session->exercises->each(fn (SessionExercise $item) => $history[$item->exercise_id]->add($item));

            // Weekreeks: weken op rij met minstens 2 trainingen
            $week = $start->startOfWeek()->toDateString();
            $perWeek[$week] = ($perWeek[$week] ?? 0) + 1;
            if ($perWeek[$week] === 2) {
                $streak = $lastFullWeek === $start->startOfWeek()->subWeek()->toDateString() ? $streak + 1 : 1;
                $lastFullWeek = $week;
            }

            $totals['sessions']++;
            $totals['hours'] += (int) $session->duration_seconds / 3600;
            $totals['volume'] += $sessionVolume;
            $totals['distance'] += $sessionDistance / 1000;
            $totals['long_sessions'] += (int) ((int) $session->duration_seconds >= 90 * 60);
            $totals['early'] += (int) ($start->hour < 7);
            $totals['late'] += (int) ($start->hour >= 21);
            $totals['weekend'] += (int) $start->isWeekend();

            $values = [
                ...$totals,
                'streak' => $perWeek[$week] >= 2 ? $streak : 0,
                'full_week' => $perWeek[$week],
                'session_volume' => $sessionVolume,
                'session_distance' => $sessionDistance / 1000,
            ];

            foreach ($badges as $progress) {
                $metric = $progress->badge->metric;
                $value = $metric->needsExercise()
                    ? ($perExercise[$metric->value][$progress->badge->exercise_id] ?? null)
                    : $values[$metric->value];

                if ($value !== null) {
                    $progress->reach($value, $session);
                }
            }
        }

        return $badges;
    }

    /**
     * Badges (niveaus) die in deze training zijn gehaald, voor de samenvatting.
     *
     * @return Collection<int, array{badge: BadgeProgress, level: int}>
     */
    public static function earnedIn(WorkoutSession $session): Collection
    {
        if ($session->status !== SessionStatus::Completed) {
            return collect();
        }

        return self::for($session->user)->flatMap(fn (BadgeProgress $badge) => array_map(
            fn (int $level) => ['badge' => $badge, 'level' => $level],
            $badge->levelsEarnedIn($session->id),
        ))->values();
    }

    /**
     * Laatst behaalde niveaus, nieuwste eerst.
     *
     * @param  Collection<int, BadgeProgress>  $badges
     * @return Collection<int, array{badge: BadgeProgress, level: int, at: CarbonInterface}>
     */
    public static function recent(Collection $badges, int $count = 3): Collection
    {
        return $badges->flatMap(fn (BadgeProgress $badge) => collect($badge->earned)
            ->map(fn (array $earned, int $level) => ['badge' => $badge, 'level' => $level, 'at' => $earned['at']])
            ->values())
            ->sortByDesc(fn (array $row) => $row['at']->getTimestamp() * 100 + $row['level'])
            ->take($count)
            ->values();
    }

    private static function volume(SessionExercise $item): float
    {
        return $item->exercise->type === ExerciseType::Strength
            ? (float) $item->sets->sum(fn ($set) => (int) $set->reps * (float) $set->weight)
            : 0;
    }

    /** Waarden per oefening: keer gedaan, zwaarste gewicht en (cardio) totale afstand */
    private static function addPerExercise(array &$perExercise, SessionExercise $item): void
    {
        $id = $item->exercise_id;
        $done = $item->exercise->type === ExerciseType::Cardio
            ? $item->cardio?->duration_seconds || $item->cardio?->distance_m
            : $item->sets->isNotEmpty();

        if (! $done) {
            return;
        }

        $perExercise['exercise_count'][$id] = ($perExercise['exercise_count'][$id] ?? 0) + 1;
        $perExercise['exercise_distance'][$id] = ($perExercise['exercise_distance'][$id] ?? 0) + ($item->cardio?->distance_m ?? 0) / 1000;

        if ($weight = $item->sets->max(fn ($set) => (float) $set->weight)) {
            $perExercise['exercise_weight'][$id] = max($perExercise['exercise_weight'][$id] ?? 0, $weight);
        }
    }
}
