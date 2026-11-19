<?php

namespace App\Support\Import;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Een CSV-export uit een andere app, één regel per set: Hevy, Strong of FitNotes.
 * Herkent zelf de app aan de kolomnamen, en ook komma of puntkomma en kg/lbs als de kolomnaam dat zegt.
 * Zegt het bestand niet in welke eenheid het gewicht staat, dan kiest de gebruiker (metrisch of imperial).
 */
final class WorkoutFile
{
    public const APPS = ['hevy' => 'Hevy', 'strong' => 'Strong', 'fitnotes' => 'FitNotes'];

    public const MAX_ROWS = 100_000;

    private const LBS = 0.45359237;

    private const MILE = 1609.344;

    /** Kolomnaam (kleine letters) => [veld, eenheid] */
    private const COLUMNS = [
        'title' => ['workout', null], 'workout name' => ['workout', null],
        'workout #' => ['number', null],
        'start_time' => ['start', null], 'date' => ['start', null],
        'end_time' => ['end', null],
        'duration' => ['duration', null], 'duration (sec)' => ['duration', null],
        'description' => ['workout_notes', null], 'workout notes' => ['workout_notes', null],
        'exercise_title' => ['exercise', null], 'exercise name' => ['exercise', null], 'exercise' => ['exercise', null],
        'category' => ['category', null],
        'exercise_notes' => ['notes', null], 'notes' => ['notes', null], 'comment' => ['notes', null],
        'set_index' => ['set', null], 'set order' => ['set', null],
        'set_type' => ['set_type', null],
        'weight' => ['weight', null], 'weight_kg' => ['weight', 'kg'], 'weight (kg)' => ['weight', 'kg'], 'weight (kgs)' => ['weight', 'kg'],
        'weight_lbs' => ['weight', 'lbs'], 'weight (lbs)' => ['weight', 'lbs'],
        'reps' => ['reps', null],
        'distance' => ['distance', null], 'distance_km' => ['distance', 'km'], 'distance (km)' => ['distance', 'km'],
        'distance_miles' => ['distance', 'mi'], 'distance (miles)' => ['distance', 'mi'],
        'distance (meters)' => ['distance', 'm'], 'distance (m)' => ['distance', 'm'],
        'distance unit' => ['distance_unit', null],
        'duration_seconds' => ['seconds', null], 'seconds' => ['seconds', null], 'time' => ['seconds', null],
        'rpe' => ['rpe', null],
    ];

    /** @param  list<array<string, mixed>>  $rows */
    private function __construct(
        public readonly string $app,
        private readonly ?string $weightUnit,
        private readonly ?string $distanceUnit,
        private readonly array $rows,
    ) {}

    /** @throws RuntimeException als het geen bestand van Hevy, Strong of FitNotes is */
    public static function read(string $path): self
    {
        $handle = @fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('unreadable');
        }

        $first = (string) fgets($handle);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first); // BOM
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = array_map(fn ($name) => strtolower(trim((string) $name)), str_getcsv(trim($first), $delimiter, '"', ''));

        $fields = [];
        $weightUnit = $distanceUnit = null;
        foreach ($header as $index => $name) {
            [$field, $unit] = self::COLUMNS[$name] ?? [null, null];
            if ($field && ! isset($fields[$field])) {
                $fields[$field] = $index;
                $weightUnit = $field === 'weight' ? $unit : $weightUnit;
                $distanceUnit = $field === 'distance' ? $unit : $distanceUnit;
            }
        }

        $app = match (true) {
            in_array('exercise_title', $header, true) => 'hevy',
            in_array('set order', $header, true) && isset($fields['workout']) => 'strong',
            in_array('category', $header, true) && in_array('exercise', $header, true) => 'fitnotes',
            default => null,
        };
        if (! $app || ! isset($fields['start'], $fields['exercise'])) {
            fclose($handle);
            throw new RuntimeException('unknown format');
        }

        $rows = [];
        while (($line = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
            $value = fn (string $field) => isset($fields[$field]) ? trim((string) ($line[$fields[$field]] ?? '')) : '';

            if ($row = self::row($app, $value, $delimiter)) {
                $rows[] = $row;
            }
        }
        fclose($handle);

        return new self($app, $weightUnit, $distanceUnit, $rows);
    }

    public function appLabel(): string
    {
        return self::APPS[$this->app];
    }

    /** Staat in het bestand niet of het gewicht in kg of lbs is? Dan kiest de gebruiker. */
    public function needsUnit(): bool
    {
        return $this->weightUnit === null && collect($this->rows)->contains(fn ($row) => $row['weight'] !== null)
            || $this->distanceUnit === null && collect($this->rows)->contains(fn ($row) => $row['distance'] !== null && $row['distance_unit'] === null);
    }

    /**
     * De trainingen, in volgorde. Gewicht in kg, afstand in meters.
     *
     * @return list<array{key: string, name: string, started_at: CarbonImmutable, ended_at: CarbonImmutable, duration: ?int, notes: ?string, exercises: list<array{name: string, notes: ?string, sets: list<array>}>}>
     */
    public function sessions(bool $imperial = false): array
    {
        $groups = [];
        foreach ($this->rows as $row) {
            $key = match ($this->app) {
                'fitnotes' => $row['start']->toDateString(),
                'strong' => $row['number'] !== '' ? $row['number'] : $row['start']->format('Y-m-d H:i').'|'.$row['workout'],
                default => $row['start']->format('Y-m-d H:i').'|'.$row['workout'],
            };
            $groups[$key][] = $row;
        }

        $sessions = [];
        foreach ($groups as $key => $rows) {
            $first = $rows[0];
            $started = $first['start'];
            $duration = $first['duration'];
            $ended = $first['end'] ?? ($duration !== null ? $started->addSeconds($duration) : $started);
            $duration ??= $first['end'] ? max(0, (int) $started->diffInSeconds($first['end'])) : null;

            $exercises = [];
            foreach ($rows as $row) {
                $name = $row['exercise'];
                $exercises[$name] ??= ['name' => $name, 'notes' => null, 'sets' => []];
                $exercises[$name]['notes'] ??= $row['notes'] !== '' ? $row['notes'] : null;
                $exercises[$name]['sets'][] = [
                    'warmup' => $row['warmup'],
                    'weight' => $row['weight'] === null ? null : round($row['weight'] * $this->weightFactor($imperial), 2),
                    'reps' => $row['reps'],
                    'seconds' => $row['seconds'],
                    'distance_m' => $row['distance'] === null ? null : (int) round($row['distance'] * $this->distanceFactor($row['distance_unit'], $imperial)),
                    'rpe' => $row['rpe'],
                ];
            }

            $name = $this->app === 'fitnotes'
                ? collect($rows)->pluck('category')->filter()->unique()->take(3)->join(' + ')
                : $first['workout'];

            $sessions[] = [
                'key' => $key,
                'name' => mb_substr($name !== '' ? $name : __('Imported training'), 0, 255),
                'started_at' => $started,
                'ended_at' => $ended,
                'duration' => $duration,
                'notes' => $first['workout_notes'] !== '' ? $first['workout_notes'] : null,
                'exercises' => array_values($exercises),
            ];
        }

        return $sessions;
    }

    /**
     * Elke oefening uit het bestand, met hoe vaak hij gedaan is en wat er is bijgehouden.
     *
     * @return array<string, array{trainings: int, weight: bool, reps: bool, seconds: bool, distance: bool}>
     */
    public function exercises(): array
    {
        $result = [];
        $seen = [];
        foreach ($this->rows as $row) {
            $name = $row['exercise'];
            $result[$name] ??= ['trainings' => 0, 'weight' => false, 'reps' => false, 'seconds' => false, 'distance' => false];
            $day = $row['start']->format('Y-m-d H:i').'|'.$row['workout'];
            if (! isset($seen[$name][$day])) {
                $seen[$name][$day] = true;
                $result[$name]['trainings']++;
            }
            $result[$name]['weight'] = $result[$name]['weight'] || $row['weight'] > 0;
            $result[$name]['reps'] = $result[$name]['reps'] || $row['reps'] > 0;
            $result[$name]['seconds'] = $result[$name]['seconds'] || $row['seconds'] > 0;
            $result[$name]['distance'] = $result[$name]['distance'] || $row['distance'] > 0;
        }

        return $result;
    }

    public function setCount(): int
    {
        return count($this->rows);
    }

    private function weightFactor(bool $imperial): float
    {
        return ($this->weightUnit ?? ($imperial ? 'lbs' : 'kg')) === 'lbs' ? self::LBS : 1.0;
    }

    private function distanceFactor(?string $rowUnit, bool $imperial): float
    {
        return match ($this->distanceUnit ?? $rowUnit ?? ($imperial ? 'mi' : 'km')) {
            'm' => 1.0,
            'mi' => self::MILE,
            default => 1000.0,
        };
    }

    /** Eén set uit het bestand, of null voor een regel zonder oefening of een rusttimer (Strong) */
    private static function row(string $app, \Closure $value, string $delimiter): ?array
    {
        $exercise = $value('exercise');
        $start = self::date($value('start'));
        $set = strtolower($value('set'));
        if ($exercise === '' || ! $start || in_array($set, ['rest timer', 'note'], true)) {
            return null;
        }
        // FitNotes kent geen tijd: midden op de dag, dan valt hij op de goede dag
        if ($app === 'fitnotes' && $start->format('H:i:s') === '00:00:00') {
            $start = $start->setTime(12, 0);
        }

        $unit = strtolower($value('distance_unit'));

        return [
            'workout' => $value('workout'),
            'number' => $value('number'),
            'start' => $start,
            'end' => self::date($value('end')),
            'duration' => self::seconds($value('duration')),
            'workout_notes' => $value('workout_notes'),
            'exercise' => mb_substr($exercise, 0, 255),
            'category' => $value('category'),
            'notes' => $value('notes'),
            'warmup' => $set === 'w' || strtolower($value('set_type')) === 'warmup',
            'weight' => self::number($value('weight'), $delimiter),
            'reps' => ($reps = self::number($value('reps'), $delimiter)) === null ? null : (int) round($reps),
            'seconds' => self::seconds($value('seconds')),
            'distance' => self::number($value('distance'), $delimiter),
            'distance_unit' => match (true) {
                $unit === '' => null,
                str_starts_with($unit, 'mi') => 'mi',
                in_array($unit, ['m', 'meter', 'meters', 'metres'], true) => 'm',
                default => 'km',
            },
            'rpe' => ($rpe = self::number($value('rpe'), $delimiter)) === null ? null : max(1, min(10, (int) round($rpe))),
        ];
    }

    private static function number(string $value, string $delimiter): ?float
    {
        if ($value === '') {
            return null;
        }
        if ($delimiter === ';' || ! str_contains($value, '.')) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /** "3600", "01:02:00", "5:30", "1h 5m" of "45m" => seconden */
    private static function seconds(string $value): ?int
    {
        if ($value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) round((float) $value);
        }
        if (preg_match('/^(\d+):(\d{1,2})(?::(\d{1,2}))?$/', $value, $m)) {
            return isset($m[3]) ? $m[1] * 3600 + $m[2] * 60 + (int) $m[3] : $m[1] * 60 + (int) $m[2];
        }
        if (preg_match('/^(?:(\d+)\s*h)?\s*(?:(\d+)\s*m(?:in)?)?\s*(?:(\d+)\s*s)?$/i', $value, $m) && trim($value) !== '') {
            return (int) ($m[1] ?? 0) * 3600 + (int) ($m[2] ?? 0) * 60 + (int) ($m[3] ?? 0);
        }

        return null;
    }

    private static function date(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'j M Y, H:i', 'j M Y H:i', 'j F Y, H:i', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.u\Z', 'Y-m-d\TH:i:s\Z'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
                if ($date) {
                    return $date;
                }
            } catch (\Throwable) {
            }
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
