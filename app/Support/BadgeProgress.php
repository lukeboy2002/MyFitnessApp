<?php

namespace App\Support;

use App\Models\Badge;
use App\Models\WorkoutSession;
use Carbon\CarbonInterface;

/**
 * Hoe ver een gebruiker is met één badge: de waarde en wanneer (in welke training) elk niveau is gehaald.
 * Achievements vult dit training na training.
 */
final class BadgeProgress
{
    /** Kleur per behaald niveau; hogere niveaus blijven diamant */
    public const TIERS = ['brons', 'zilver', 'goud', 'platina', 'diamant'];

    public int $key;

    public string $name;

    public string $icon;

    public string $unit;

    /** @var array<int, float|int> */
    public array $thresholds;

    public float $value = 0;

    /** @var array<int, array{at: CarbonInterface, session_id: int}> per niveau (index in) */
    public array $earned = [];

    public function __construct(public Badge $badge)
    {
        $this->key = $badge->id;
        $this->name = $badge->name;
        $this->icon = $badge->icon;
        $this->unit = $badge->metric->unit();
        $this->thresholds = array_values($badge->thresholds);
    }

    /** Nieuwe waarde na een training; hogere niveaus die nu gehaald zijn krijgen deze training als datum */
    public function reach(float $value, WorkoutSession $session): void
    {
        $this->value = max($this->value, $value);

        foreach ($this->thresholds as $level => $threshold) {
            if (! isset($this->earned[$level]) && $this->value >= $threshold) {
                $this->earned[$level] = ['at' => $session->started_at, 'session_id' => $session->id];
            }
        }
    }

    public function level(): int
    {
        return count($this->earned);
    }

    public function isEarned(): bool
    {
        return $this->earned !== [];
    }

    public function next(): float|int|null
    {
        return $this->thresholds[$this->level()] ?? null;
    }

    /** Procent op weg naar het volgende niveau */
    public function progress(): int
    {
        return ($next = $this->next()) ? (int) min(100, floor($this->value / $next * 100)) : 100;
    }

    /** Naam van het hoogste behaalde niveau, of van het eerste als er nog niets is behaald */
    public function title(): string
    {
        return $this->labelFor(max(0, $this->level() - 1));
    }

    public function labelFor(int $level): string
    {
        return $this->badge->labelFor($level);
    }

    public function tier(?int $level = null): ?string
    {
        $level ??= $this->level() - 1;

        return $level < 0 ? null : self::TIERS[min($level, count(self::TIERS) - 1)];
    }

    /** Waarde zoals op het scherm, bv. "12.340" kg of "94,6" uur */
    public function format(float|int $value): string
    {
        return $this->badge->metric->format($value);
    }

    public function earnedAt(): ?CarbonInterface
    {
        return $this->isEarned() ? $this->earned[$this->level() - 1]['at'] : null;
    }

    /** Niveaus die in deze training zijn gehaald */
    public function levelsEarnedIn(int $sessionId): array
    {
        return array_keys(array_filter($this->earned, fn (array $earned) => $earned['session_id'] === $sessionId));
    }
}
