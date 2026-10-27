<?php

namespace App\Support;

use App\Models\User;

/** Vijf hartslagzones als percentage van je (geschatte) maximale hartslag */
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

    public function __construct(public int $max) {}

    /** Null zolang je geboortedatum niet in je profiel staat */
    public static function forUser(?User $user): ?self
    {
        $max = $user?->maxHeartRate();

        return $max ? new self($max) : null;
    }

    /** @return list<array{number: int, name: string, from: int, to: int}> met slagen per minuut */
    public function all(): array
    {
        return collect(self::ZONES)->map(fn ($zone, $number) => [
            'number' => $number,
            'name' => $zone[0],
            'from' => (int) round($this->max * $zone[1] / 100),
            'to' => (int) round($this->max * $zone[2] / 100),
        ])->values()->all();
    }

    public function percent(int|float $bpm): int
    {
        return (int) round($bpm / $this->max * 100);
    }

    /** De zone van een hartslag, of null onder zone 1 */
    public function zoneFor(int|float|null $bpm): ?array
    {
        if (! $bpm) {
            return null;
        }

        $percent = $bpm / $this->max * 100;
        $number = collect(self::ZONES)->filter(fn ($zone) => $percent >= $zone[1])->keys()->last();

        return $number ? $this->all()[$number - 1] + ['percent' => $this->percent($bpm)] : null;
    }
}
