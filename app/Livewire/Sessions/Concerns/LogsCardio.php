<?php

namespace App\Livewire\Sessions\Concerns;

use App\Enums\CardioMetric;
use App\Models\CardioLog;
use App\Models\SessionExercise;
use App\Models\WorkoutSession;
use App\Support\ExerciseRecords;
use App\Support\Locale;

/**
 * Trainingsscherm: cardio-gegevens, tussentijden per km en cardio afronden.
 * Hoort bij Sessions\Run; gebruikt daar $session, findExercise() en decimal().
 */
trait LogsCardio
{
    /** Invoer per cardio-oefening: [session_exercise_id => ['time' => 'mm:ss', 'distance' => km, 'metric' => .., ...]] */
    public array $cardio = [];

    /** Tussentijden per km: [session_exercise_id => [km => 'mm:ss']] */
    public array $splits = [];

    public function updatedCardio($value, string $key): void
    {
        [$id, $field] = explode('.', $key);
        $log = $this->findExercise((int) $id)->cardio()->firstOrCreate();
        $value = $this->decimal($value);

        $column = match (true) {
            $field === 'time' => ['duration_seconds', $this->clockSeconds($value)],
            $field === 'distance' => ['distance_m', filled($value) && is_numeric($value) ? (int) round($value * 1000) : null],
            $field === 'metric' => ['metric', CardioMetric::tryFrom((string) $value)],
            $field === 'pace' => ['pace_seconds', $this->clockSeconds($value)],
            in_array($field, ['rotations', 'floors', 'stroke_rate']) => [$field, filled($value) && ctype_digit((string) $value) ? (int) $value : null],
            in_array($field, ['metric_value', 'mets', 'incline']) => [$field, filled($value) && is_numeric($value) ? $value : null],
            array_key_exists($field, CardioLog::FIELDS) => [$field, filled($value) && is_numeric($value) ? (int) $value : null],
            default => null,
        };

        if (! $column || (filled($value) && $column[1] === null && $field !== 'metric')) {
            $this->addError("cardio.{$id}.{$field}", __('Invalid value'));

            return;
        }

        $this->resetErrorBag("cardio.{$id}.{$field}");
        $log->update([$column[0] => $column[1]]);
    }

    /** Tussentijd: hoe lang je over km $number deed */
    public function updatedSplits($value, string $key): void
    {
        // Een lege lijst stuurt de browser in zijn geheel mee: dan elke km apart opslaan
        if (! str_contains($key, '.')) {
            foreach ((array) $value as $number => $time) {
                $this->updatedSplits($time, "{$key}.{$number}");
            }

            return;
        }

        [$id, $number] = array_map('intval', explode('.', $key));
        $log = $this->findExercise($id)->cardio()->firstOrCreate();
        $seconds = $this->clockSeconds($value);

        if ($number < 1) {
            unset($this->splits[$id][$number]);

            return;
        }

        if (filled($value) && ! $seconds) {
            $this->addError("splits.{$id}.{$number}", __('Invalid time'));

            return;
        }

        $this->resetErrorBag("splits.{$id}.{$number}");
        $seconds
            ? $log->splits()->updateOrCreate(['number' => $number], ['duration_seconds' => $seconds])
            : $log->splits()->where('number', $number)->delete();
    }

    /** Knop "Volgende km": een nieuw vakje voor de volgende tussentijd */
    public function addSplit(int $sessionExerciseId): void
    {
        $this->findExercise($sessionExerciseId);

        $splits = $this->splits[$sessionExerciseId] ?? [];
        $this->splits[$sessionExerciseId] = $splits + [($splits ? max(array_keys($splits)) : 0) + 1 => null];
        $this->dispatch('split-added', id: $sessionExerciseId);
    }

    /** Tussentijd weghalen; de km's erna schuiven een plek op */
    public function removeSplit(int $sessionExerciseId, int $number): void
    {
        $log = $this->findExercise($sessionExerciseId)->cardio()->firstOrCreate();

        $log->splits()->where('number', $number)->delete();
        foreach ($log->splits()->where('number', '>', $number)->get() as $split) {
            $split->decrement('number');
        }

        $times = $this->splits[$sessionExerciseId] ?? [];
        unset($times[$number]);
        ksort($times);
        $this->splits[$sessionExerciseId] = $times ? array_combine(range(1, count($times)), array_values($times)) : [];
        $this->resetErrorBag("splits.{$sessionExerciseId}");
    }

    /** Cardio-oefening afronden of weer openzetten */
    public function toggleCardio(int $sessionExerciseId): void
    {
        $item = $this->findExercise($sessionExerciseId);
        $item->update(['completed_at' => $item->completed_at ? null : now()]);

        $new = $item->completed_at
            ? ExerciseRecords::build($this->session, $item->exercise_id, $item->exercise->type)->cardioRecords($item->cardio()->with('splits')->first())
            : [];
        if ($new) {
            $this->dispatch('record', title: count($new) > 1 ? __('New records!') : __('New record!'), text: $item->exercise->name.' · '.Locale::lower(implode(', ', $new)));
        }
    }

    /** Invoervelden van een cardio-oefening uit de opgeslagen gegevens */
    private function fillCardio(SessionExercise $item): void
    {
        if (! $log = $item->cardio) {
            return;
        }

        $this->cardio[$item->id] = [
            'time' => $log->duration_seconds ? sprintf('%d:%02d', intdiv($log->duration_seconds, 60), $log->duration_seconds % 60) : null,
            'distance' => $log->distance_m ? WorkoutSession::formatNumber($log->distance_m / 1000) : null,
            'metric' => $log->metric?->value,
            'metric_value' => filled($log->metric_value) ? WorkoutSession::formatNumber($log->metric_value) : null,
            'rotations' => $log->rotations,
            'floors' => $log->floors,
            'stroke_rate' => $log->stroke_rate,
            'pace' => CardioLog::pace($log->pace_seconds),
            ...collect(CardioLog::FIELDS)->keys()->mapWithKeys(fn ($f) => [$f => filled($log->{$f}) ? WorkoutSession::formatNumber($log->{$f}) : null]),
        ];
        $this->splits[$item->id] = $log->splits->mapWithKeys(fn ($split) => [$split->number => CardioLog::pace($split->duration_seconds)])->all();
    }

    /** Klok-tijd: "5:32" of "1:02:10" → seconden; een los getal telt als minuten */
    private function clockSeconds($value): ?int
    {
        return $this->seconds(is_string($value) ? str_replace(['.', ','], ':', trim($value)) : $value);
    }

    /** "20", "20:30" of "1:05:00" → seconden */
    private function seconds($value): ?int
    {
        if (blank($value)) {
            return null;
        }

        $parts = array_map('trim', explode(':', (string) $value));
        if (count($parts) > 3 || collect($parts)->contains(fn ($p) => ! ctype_digit($p))) {
            return null;
        }

        return match (count($parts)) {
            1 => (int) $parts[0] * 60,
            2 => (int) $parts[0] * 60 + (int) $parts[1],
            3 => (int) $parts[0] * 3600 + (int) $parts[1] * 60 + (int) $parts[2],
        };
    }
}
