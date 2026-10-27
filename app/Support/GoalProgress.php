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
                fn ($kilos) => TrainingStats::kilos($kilos), 'kg volume',
                'Train elke spiergroep twee keer per week en verhoog steeds je herhalingen of gewicht.',
            ),
            FitnessGoal::Strength => self::compare(
                FitnessGoal::Strength,
                self::recordsBetween($user, $from, $now),
                self::recordsBetween($user, $from->subDays(self::DAYS), $from),
                fn ($count) => (string) $count, 'records',
                'Zware sets van 3 tot 6 herhalingen met 2 tot 3 minuten rust maken je het sterkst.',
            ),
            FitnessGoal::Endurance => self::compare(
                FitnessGoal::Endurance,
                $stats->totals($from)['distance_m'] / 1000,
                $stats->totals($from->subDays(self::DAYS), $from)['distance_m'] / 1000,
                fn ($km) => WorkoutExercise::number(round($km, 1)), 'km cardio',
                self::zoneTip($user, 2, 'Doe het meeste rustig in zone 2{zone} en één keer per week een intervaltraining.'),
            ),
            FitnessGoal::StayFit => self::compare(
                FitnessGoal::StayFit,
                $stats->totals($from)['count'],
                $stats->totals($from->subDays(self::DAYS), $from)['count'],
                fn ($count) => (string) $count, 'trainingen',
                'Een paar keer per week bewegen is belangrijker dan af en toe heel hard trainen.',
            ),
        };
    }

    /** Laatste weging, verschil in 30 dagen en hoe ver je bent richting je streefgewicht */
    private static function loseWeight(User $user, CarbonImmutable $from): self
    {
        $weights = $user->bodyWeights()->orderBy('measured_on')->get();
        $tip = self::zoneTip($user, 2, 'Combineer kracht met rustige cardio in zone 2{zone}: zo verbrand je vet en houd je je spieren.');
        $link = ['label' => 'Weging invullen', 'url' => route('profile.edit').'#gewicht'];

        if ($weights->isEmpty()) {
            return new self(FitnessGoal::LoseWeight, null, null, 'Weeg jezelf om je voortgang te zien.', null, $tip, $link);
        }

        $latest = $weights->last();
        $before = $weights->filter(fn ($weight) => $weight->measured_on->lt($from))->last() ?? $weights->first();
        $change = round($latest->weight_kg - $before->weight_kg, 1);
        $n = fn ($v) => WorkoutExercise::number($v);

        $detail = [];
        if ($latest->isNot($before)) {
            $detail[] = ($change > 0 ? '+' : '').$n($change).' kg sinds '.$before->measured_on->translatedFormat('j M');
        }

        $percent = null;
        if ($target = $user->target_weight_kg) {
            $toGo = round($latest->weight_kg - $target, 1);
            $detail[] = $toGo > 0 ? 'nog '.$n($toGo).' kg tot '.$n($target).' kg' : 'streefgewicht bereikt';

            $start = (float) $weights->first()->weight_kg;
            $percent = $start > $target ? (int) max(0, min(100, round(($start - $latest->weight_kg) / ($start - $target) * 100))) : ($toGo > 0 ? 0 : 100);
        }

        return new self(FitnessGoal::LoseWeight, $n($latest->weight_kg), 'kg', implode(' · ', $detail) ?: null, $percent, $tip, $link);
    }

    /** De laatste 30 dagen naast de 30 dagen ervoor */
    private static function compare(FitnessGoal $goal, float|int $now, float|int $before, callable $format, string $unit, string $tip): self
    {
        $detail = match (true) {
            $before > 0 => (($difference = (int) round(($now - $before) / $before * 100)) >= 0 ? '+' : '').$difference.'% t.o.v. de 30 dagen ervoor ('.$format($before).')',
            $now > 0 => 'De 30 dagen ervoor: nog niets',
            default => 'Nog niets in de laatste 30 dagen',
        };

        return new self($goal, $format($now), $unit, $detail, null, $tip, ['label' => 'Progress', 'url' => route('progress.index')]);
    }

    /** Tip met de hartslag van een zone erin, als die bekend is */
    private static function zoneTip(User $user, int $number, string $tip): string
    {
        $zone = HeartRateZones::forUser($user)?->all()[$number - 1];

        return str_replace('{zone}', $zone ? ' ('.$zone['from'].' tot '.$zone['to'].' bpm)' : '', $tip);
    }

    /** Aantal records (zoals op het trainingsscherm) in afgeronde trainingen tussen $from en $until */
    private static function recordsBetween(User $user, CarbonImmutable $from, CarbonImmutable $until): int
    {
        $sessions = WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where('status', SessionStatus::Completed)
            ->where('started_at', '<', $until)
            ->with(['exercises.exercise', 'exercises.sets' => fn ($q) => $q->where('is_completed', true), 'exercises.cardio.splits'])
            ->orderBy('started_at')->orderBy('id')
            ->get();

        $history = [];
        $count = 0;

        foreach ($sessions as $session) {
            foreach ($session->exercises as $item) {
                $history[$item->exercise_id] ??= new ExerciseRecords($item->exercise->type);
                if ($session->started_at->gte($from)) {
                    $count += $history[$item->exercise_id]->countRecords($item);
                }
            }
            $session->exercises->each(fn ($item) => $history[$item->exercise_id]->add($item));
        }

        return $count;
    }
}
