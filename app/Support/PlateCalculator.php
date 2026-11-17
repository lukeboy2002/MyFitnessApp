<?php

namespace App\Support;

use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Schijvencalculator: welke schijven je per kant op de stang zet.
 * Alleen voor oefeningen met een stang of een machine waar je schijven op schuift ("Plated", T-bar, Iso-Lateral);
 * machines met een pen in de gewichtenstapel hebben dit niet nodig.
 */
final class PlateCalculator
{
    /** Standaardgewicht van de stangen (kg); in het profiel aan te passen (users.bar_weights) */
    public const BARS = ['bar' => 20, 'ez_bar' => 10, 'trap_bar' => 25];

    /** Schijven in de sportschool, zwaarste eerst (kg) */
    public const PLATES = [25, 20, 15, 10, 5, 2.5, 1.25];

    public function __construct(public readonly float $bar) {}

    /** Calculator voor deze oefening, of null als er geen schijven op gaan; met de stanggewichten van $user */
    public static function for(Exercise $exercise, ?User $user = null): ?self
    {
        $name = Str::lower($exercise->name);
        $bar = fn (string $type) => new self((float) ($user?->bar_weights[$type] ?? self::BARS[$type]));
        // Alleen standaardmateriaal; bij eigen materiaal weten we niet of er schijven op gaan
        $kind = $exercise->equipment?->isStandard() ? $exercise->equipment->slug : null;

        return match (true) {
            $kind === Equipment::BARBELL && Str::contains($name, 'landmine') => null,   // schijven aan één kant
            $kind === Equipment::BARBELL && Str::contains($name, 'ez bar') => $bar('ez_bar'),
            $kind === Equipment::BARBELL && Str::contains($name, 'trap bar') => $bar('trap_bar'),
            $kind === Equipment::BARBELL => $bar('bar'),
            $kind === Equipment::PLATE && Str::contains($name, ['plated', 't bar', 'iso-lateral', 'leg press']) => new self(0),
            default => null,
        };
    }

    /**
     * Schijven per kant voor dit totaalgewicht.
     *
     * @return array{plates: list<float>, rest: float}|null null als het gewicht lichter is dan de stang;
     *                                                      rest = wat per kant overblijft omdat er geen kleinere schijf is
     */
    public function perSide(float $total): ?array
    {
        if ($total < $this->bar || $total <= 0) {
            return null;
        }

        $side = round(($total - $this->bar) / 2, 3);
        $plates = [];

        foreach (self::PLATES as $plate) {
            while ($side + 0.001 >= $plate) {
                $plates[] = (float) $plate;
                $side = round($side - $plate, 3);
            }
        }

        return ['plates' => $plates, 'rest' => $side];
    }

    /** Kleur van een schijf (zoals de gekleurde wedstrijdschijven) */
    public static function color(float $plate): string
    {
        return match ((string) $plate) {
            '25' => 'bg-red-600 text-white',
            '20' => 'bg-blue-600 text-white',
            '15' => 'bg-amber-400 text-brand-950',
            '10' => 'bg-emerald-600 text-white',
            '5' => 'bg-white text-ink ring-1 ring-slate-300',
            default => 'bg-slate-500 text-white',
        };
    }
}
