<?php

namespace App\Support;

use App\Enums\FitnessGoal;
use App\Enums\SessionStatus;
use App\Models\User;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;

/**
 * Voortgang richting je doel (profiel): de laatste 30 dagen vergeleken met de 30 dagen ervoor,
 * met een tip die bij het doel past. Voor de kaart op het dashboard.
 */
class GoalProgress
{
    public const DAYS = 30;

    public function __construct(
        public FitnessGoal $goal,
        public ?string $value,
        public ?string $unit,
        public ?string $detail,
        public ?int $percent,
        public string $tip,
        public array $link,
    ) {}

    public static function for(User $user): ?self
    {
        if (! $user->goal) {
            return null;
        }

        $now = CarbonImmutable::now();
        $from = $now->subDays(self::DAYS)->startOfDay();
        $stats = new TrainingStats($user);

        return match ($user->goal) {
            FitnessGoal::LoseWeight => self::loseWeight($user, $from),
            FitnessGoal::BuildMuscle => self::compare(
                FitnessGoal::BuildMuscle,
                $stats->totals($from)['volume'],
                $stats->totals($from->subDays(self::DAYS), $from)['volume'],
                fn ($kilos) => TrainingStats::kilos($kilos), __('kg volume'),
                __('Train every muscle group twice a week and keep increasing your reps or weight.'),
            ),
            FitnessGoal::Strength => self::compare(
                FitnessGoal::Strength,
                self::recordsBetween($user, $from, $now),
                self::recordsBetween($user, $from->subDays(self::DAYS), $from),
                fn ($count) => (string) $count, __('records'),
                __('Heavy sets of 3 to 6 reps with 2 to 3 minutes of rest make you strongest.'),
            ),
            FitnessGoal::Endurance => self::compare(
                FitnessGoal::Endurance,
                $stats->totals($from)['distance_m'] / 1000,
                $stats->totals($from->subDays(self::DAYS), $from)['distance_m'] / 1000,
                fn ($km) => WorkoutExercise::number(round($km, 1)), __('km cardio'),
                self::zoneTip($user, 2, 'Do most of it easy in zone 2:zone and an interval training once a week.'),
            ),
            FitnessGoal::StayFit => self::compare(
                FitnessGoal::StayFit,
                $stats->totals($from)['count'],
                $stats->totals($from->subDays(self::DAYS), $from)['count'],
                fn ($count) => (string) $count, __('trainings'),
                __('Moving a few times a week matters more than training very hard now and then.'),
            ),
        };
    }

    /** Laatste weging, verschil in 30 dagen en hoe ver je bent richting je streefgewicht */
    private static function loseWeight(User $user, CarbonImmutable $from): self
    {
        $weights = $user->bodyWeights()->orderBy('measured_on')->get();
        $tip = self::zoneTip($user, 2, 'Combine strength with easy cardio in zone 2:zone: that way you burn fat and keep your muscles.');
        $link = ['label' => __('Log weight'), 'url' => route('profile.edit').'#gewicht'];

        if ($weights->isEmpty()) {
            return new self(FitnessGoal::LoseWeight, null, null, __('Weigh yourself to see your progress.'), null, $tip, $link);
        }

        $latest = $weights->last();
        $before = $weights->filter(fn ($weight) => $weight->measured_on->lt($from))->last() ?? $weights->first();
        $change = round($latest->weight_kg - $before->weight_kg, 1);
        $n = fn ($v) => WorkoutExercise::number($v);

        $detail = [];
        if ($latest->isNot($before)) {
            $detail[] = __(':change kg since :date', ['change' => ($change > 0 ? '+' : '').$n($change), 'date' => $before->measured_on->translatedFormat('j M')]);
        }

        $percent = null;
        if ($target = $user->target_weight_kg) {
            $toGo = round($latest->weight_kg - $target, 1);
            $detail[] = $toGo > 0 ? __(':amount kg to go until :target kg', ['amount' => $n($toGo), 'target' => $n($target)]) : __('target weight reached');

            $start = (float) $weights->first()->weight_kg;
            $percent = $start > $target ? (int) max(0, min(100, round(($start - $latest->weight_kg) / ($start - $target) * 100))) : ($toGo > 0 ? 0 : 100);
        }

        return new self(FitnessGoal::LoseWeight, $n($latest->weight_kg), 'kg', implode(' · ', $detail) ?: null, $percent, $tip, $link);
    }

    /** De laatste 30 dagen naast de 30 dagen ervoor */
    private static function compare(FitnessGoal $goal, float|int $now, float|int $before, callable $format, string $unit, string $tip): self
    {
        $detail = match (true) {
            $before > 0 => __(':difference% compared to the 30 days before (:before)', ['difference' => (($difference = (int) round(($now - $before) / $before * 100)) >= 0 ? '+' : '').$difference, 'before' => $format($before)]),
            $now > 0 => __('The 30 days before: nothing yet'),
            default => __('Nothing yet in the last 30 days'),
        };

        return new self($goal, $format($now), $unit, $detail, null, $tip, ['label' => __('Progress'), 'url' => route('progress.index')]);
    }

    /** Tip met de hartslag van een zone erin, als die bekend is */
    private static function zoneTip(User $user, int $number, string $tip): string
    {
        $zone = HeartRateZones::forUser($user)?->all()[$number - 1];

        return __($tip, ['zone' => $zone ? ' '.__('(:from to :to bpm)', ['from' => $zone['from'], 'to' => $zone['to']]) : '']);
    }

    /** Aantal records (zoals op het trainingsscherm) in afgeronde trainingen tussen $from en $until */
    private static function recordsBetween(User $user, CarbonImmutable $from, CarbonImmutable $until): int
    {
        return collect(self::recordsPerSession($user))
            ->filter(fn (array $row) => $row['at'] >= $from->getTimestamp() && $row['at'] < $until->getTimestamp())
            ->sum('count');
    }

    /**
     * Records per afgeronde training, oudste eerst. Bewaard tot er een training bij komt of af gaat.
     *
     * @return array<int, array{at: int, count: int}>
     */
    private static function recordsPerSession(User $user): array
    {
        return TrainingCache::remember($user->id, 'records-per-session', function () use ($user) {
            $sessions = WorkoutSession::query()
                ->where('user_id', $user->id)
                ->where('status', SessionStatus::Completed)
                ->with(['exercises.exercise', 'exercises.sets' => fn ($q) => $q->where('is_completed', true), 'exercises.cardio.splits'])
                ->orderBy('started_at')->orderBy('id')
                ->get();

            $history = [];
            $rows = [];

            foreach ($sessions as $session) {
                $count = 0;
                foreach ($session->exercises as $item) {
                    $history[$item->exercise_id] ??= new ExerciseRecords($item->exercise->type);
                    $count += $history[$item->exercise_id]->countRecords($item);
                }
                $session->exercises->each(fn ($item) => $history[$item->exercise_id]->add($item));
                $rows[] = ['at' => $session->started_at->getTimestamp(), 'count' => $count];
            }

            return $rows;
        });
    }
}
