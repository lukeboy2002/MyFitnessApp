<?php

namespace App\Support;

use App\Models\User;

/**
 * Vijf hartslagzones. Zonder rusthartslag als percentage van je maximale hartslag,
 * met rusthartslag volgens Karvonen: rust + percentage x (max - rust), de hartslagreserve.
 */
class HeartRateZones
{
    /** nummer => [naam, vanaf %, tot %] */
    public const ZONES = [
        1 => ['Herstel', 50, 60],
        2 => ['Vetverbranding', 60, 70],
        3 => ['Aeroob', 70, 80],
        4 => ['Anaeroob', 80, 90],
        5 => ['Maximaal', 90, 100],
    ];

    public function __construct(public int $max, public ?int $rest = null)
    {
        if ($this->rest !== null && ($this->rest <= 0 || $this->rest >= $this->max)) {
            $this->rest = null;
        }
    }

    /** Null zolang er geen max bekend is (geen eigen max en geen geboortedatum) */
    public static function forUser(?User $user): ?self
    {
        $max = $user?->maxHeartRate();

        return $max ? new self($max, $user->restingHeartRate()) : null;
    }

    /** Rekenen we met je rusthartslag (Karvonen)? */
    public function usesReserve(): bool
    {
        return $this->rest !== null;
    }

    /** Uitleg bij het percentage, bv. in een tooltip */
    public function basis(): string
    {
        return $this->usesReserve()
            ? 'van je hartslagreserve (rust '.$this->rest.', max '.$this->max.' bpm)'
            : 'van je max. hartslag ('.$this->max.' bpm)';
    }

    /** @return list<array{number: int, name: string, from: int, to: int}> met slagen per minuut */
    public function all(): array
    {
        return collect(self::ZONES)->map(fn ($zone, $number) => [
            'number' => $number,
            'name' => $zone[0],
            'from' => $this->bpmAt($zone[1]),
            'to' => $this->bpmAt($zone[2]),
        ])->values()->all();
    }

    public function percent(int|float $bpm): int
    {
        return (int) round($this->exactPercent($bpm));
    }

    /** De zone van een hartslag, of null onder zone 1 */
    public function zoneFor(int|float|null $bpm): ?array
    {
        if (! $bpm) {
            return null;
        }

        $percent = $this->exactPercent($bpm);
        $number = collect(self::ZONES)->filter(fn ($zone) => $percent >= $zone[1])->keys()->last();

        return $number ? $this->all()[$number - 1] + ['percent' => $this->percent($bpm)] : null;
    }

    private function bpmAt(int $percent): int
    {
        $rest = $this->rest ?? 0;

        return (int) round($rest + ($this->max - $rest) * $percent / 100);
    }

    private function exactPercent(int|float $bpm): float
    {
        $rest = $this->rest ?? 0;

        return ($bpm - $rest) / ($this->max - $rest) * 100;
    }
}
