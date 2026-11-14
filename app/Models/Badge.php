<?php

namespace App\Models;

use App\Enums\BadgeMetric;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Een badge met niveaus; de admin beheert ze op de pagina Badgebeheer */
#[Fillable(['name', 'icon', 'metric', 'exercise_id', 'thresholds', 'label', 'first_label', 'sort_order', 'is_active'])]
class Badge extends Model
{
    /** Iconen om uit te kiezen */
    public const ICONS = [
        'medal' => 'Medal', 'trophy' => 'Trophy', 'dumbbell' => 'Dumbbell', 'bolt' => 'Strength', 'run' => 'Running',
        'clock' => 'Clock', 'calendar' => 'Calendar (page)', 'check' => 'Tick', 'heart' => 'Heart', 'sun' => 'Sun', 'moon' => 'Moon',
    ];

    protected function casts(): array
    {
        return [
            'metric' => BadgeMetric::class,
            'thresholds' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected $attributes = ['exercise_id' => null, 'first_label' => null, 'is_active' => true];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    /** Naam van een niveau, bv. "25 trainingen" of (eerste niveau) "Eerste training" */
    public function labelFor(int $level): string
    {
        if ($level === 0 && filled($this->first_label)) {
            return __($this->first_label);
        }

        return str_replace('{n}', $this->metric->format($this->thresholds[$level] ?? 0), __($this->label));
    }

    /** Naam in de taal van de gebruiker: de standaardbadges hebben een vertaling, eigen namen blijven zoals ze zijn */
    public function displayName(): string
    {
        return __($this->name);
    }

    /** Wat de badge meet, bv. "Aantal trainingen" of "Zwaarste gewicht bij een oefening: Leg Press" */
    public function description(): string
    {
        return $this->metric->label().($this->exercise ? ': '.$this->exercise->name : '');
    }
}
