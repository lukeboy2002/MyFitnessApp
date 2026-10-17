<?php

namespace App\Models\Concerns;

use App\Enums\CardioMetric;

/**
 * Doelkolommen (target_sets, target_reps, target_weight, rest_seconds, target_duration_seconds,
 * target_metric, target_metric_value) van workout_exercises en session_exercises.
 * Het model heeft een exercise()-relatie.
 */
trait HasTargets
{
    public function initializeHasTargets(): void
    {
        $this->mergeCasts([
            'target_weight' => 'decimal:2',
            'target_metric' => CardioMetric::class,
            'target_metric_value' => 'decimal:1',
        ]);
    }

    /** Doel als tekst, bv. "3 × 10 · 40 kg" of "20 min · weerstand 8" */
    public function targetSummary(): ?string
    {
        $type = $this->exercise->type;
        $parts = [];

        if ($type->usesSets() && $this->target_sets) {
            $parts[] = $this->target_sets.' ×'.($type->usesReps() && $this->target_reps ? ' '.$this->target_reps : '');
        }

        if (($type->usesWeight() || $type->usesExtraWeight()) && (float) $this->target_weight !== 0.0) {
            $kg = static::number($this->target_weight).' kg';
            $parts[] = $type->usesExtraWeight() && $this->target_weight > 0 ? '+'.$kg : $kg;
        }

        if ($type->usesDuration() && $this->target_duration_seconds) {
            $parts[] = static::duration($this->target_duration_seconds);
        }

        if ($type->usesMetric() && $this->target_metric && filled($this->target_metric_value)) {
            $parts[] = $this->target_metric->format($this->target_metric_value);
        }

        return $parts ? implode(' · ', $parts) : null;
    }

    /** 90 → "1:30 min", 120 → "2 min", 45 → "45 sec" */
    public static function duration(int $seconds): string
    {
        return $seconds % 60 === 0 ? ($seconds / 60).' min' : ($seconds >= 60 ? intdiv($seconds, 60).':'.str_pad($seconds % 60, 2, '0', STR_PAD_LEFT).' min' : $seconds.' sec');
    }

    /** 42.50 → "42,5" */
    public static function number(float|string|null $value): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.'));
    }
}
