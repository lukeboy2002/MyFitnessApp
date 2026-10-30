<?php

namespace App\Support;

use App\Enums\ExerciseType;
use App\Models\SetLog;
use App\Models\WorkoutSession;

/**
 * Voorstel voor vandaag op basis van de vorige keer ("dubbele progressie"):
 * eerst elke training een herhaling erbij, tot het doel + REP_RANGE (bv. 12 → 15),
 * dan WEIGHT_STEP kg zwaarder en weer terug naar het doel.
 */
final class ProgressionHint
{
    /** Zoveel herhalingen boven het doel ga je zwaarder, bv. 12 + 3 = 15 */
    public const REP_RANGE = 3;

    /** Zwaarder: meestal de volgende stap op de machine */
    public const WEIGHT_STEP = 5;

    /** Doel als de oefening er geen heeft */
    public const DEFAULT_REPS = 12;

    public function __construct(
        public string $text,
        public ?float $weight,
        public int $reps,
    ) {}

    /** @param  array<int, SetLog>  $lastSets  afgevinkte sets van de vorige keer */
    public static function for(ExerciseType $type, array $lastSets, ?int $targetReps): ?self
    {
        $sets = collect($lastSets)->filter(fn (SetLog $set) => $set->reps > 0)->values();

        if (! $type->usesReps() || $sets->isEmpty()) {
            return null;
        }

        $low = $targetReps ?: self::DEFAULT_REPS;
        $high = $low + self::REP_RANGE;
        $n = fn ($value) => WorkoutSession::formatNumber($value);

        // Het zwaarste gewicht van de vorige keer telt; lichtere sets (bv. opwarmen) niet
        // Bij lichaamsgewicht telt 0 kg extra als "geen extra gewicht"
        $weights = $sets->map(fn (SetLog $set) => filled($set->weight) && ($type === ExerciseType::Strength || (float) $set->weight !== 0.0) ? (float) $set->weight : null);
        $weight = $weights->filter(fn ($w) => $w !== null)->max();
        $top = $weight === null ? $sets : $sets->filter(fn (SetLog $set, int $i) => $weights[$i] === $weight)->values();
        $fewest = (int) $top->min('reps');

        $reps = $top->pluck('reps');
        $last = ($reps->unique()->count() === 1 ? $top->count().'×'.$reps->first() : $reps->implode(', '))
            .($weight ? ' met '.$n($weight).' kg' : '');

        // Alle sets op het hoogste aantal: zwaarder. Zonder gewicht (lichaamsgewicht) gewoon meer herhalingen.
        if ($fewest >= $high && $weight !== null) {
            $heavier = round($weight + self::WEIGHT_STEP, 1);

            return new self(
                "Vorige keer {$last} gehaald. Probeer {$n($heavier)} kg".($type === ExerciseType::Bodyweight && $heavier < 0 ? ' (minder hulp)' : '')." en weer {$low} herhalingen.",
                $heavier,
                $low,
            );
        }

        $goal = max($fewest + 1, $low);

        return new self(
            "Vorige keer {$last}. Probeer {$goal} herhalingen".($weight !== null ? ' met '.$n($weight).' kg' : '').' in elke set.',
            $weight,
            $goal,
        );
    }
}
