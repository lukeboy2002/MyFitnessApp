<?php

namespace App\Livewire\Sessions\Concerns;

use App\Models\SessionExercise;
use App\Models\SetLog;
use App\Support\ExerciseRecords;
use Illuminate\Support\Carbon;

/**
 * Trainen zonder bereik: wat je zonder verbinding invulde en afvinkte, bewaart de telefoon
 * (resources/js/offline-training.js) en stuurt het hierheen zodra er weer verbinding is.
 * Elke wijziging is de eindstand van een set of cardio-oefening (niet "vinkje omzetten"),
 * dus twee keer versturen kan geen kwaad. Hoort bij Sessions\Run; gebruikt LogsSets en LogsCardio.
 */
trait SyncsOfflineChanges
{
    /** Zo veel wijzigingen per keer is ruim genoeg voor een training */
    private const OFFLINE_MAX = 300;

    /**
     * sets: [set_id => ['weight' =>, 'reps' =>, 'seconds' =>, 'warmup' => bool, 'done' => bool, 'at' => ms]]
     * cardio: [session_exercise_id => ['fields' => [veld => waarde], 'done' => bool, 'at' => ms]]
     * splits: [session_exercise_id => [km => 'mm:ss']]
     */
    public function syncOffline(array $changes): void
    {
        $this->ensureRunning();

        $sets = array_slice((array) ($changes['sets'] ?? []), 0, self::OFFLINE_MAX, true);
        $cardio = array_slice((array) ($changes['cardio'] ?? []), 0, self::OFFLINE_MAX, true);
        $splits = array_slice((array) ($changes['splits'] ?? []), 0, self::OFFLINE_MAX, true);

        $ticked = [];
        foreach ($sets as $id => $change) {
            if ($set = $this->offlineSet((int) $id)) {
                $ticked[] = $this->syncSet($set, (array) $change);
            }
        }

        foreach ($cardio as $id => $change) {
            if ($item = $this->offlineExercise((int) $id)) {
                $this->syncCardio($item, (array) $change);
            }
        }

        foreach ($splits as $id => $times) {
            if ($this->offlineExercise((int) $id)) {
                foreach (array_slice((array) $times, 0, 100, true) as $number => $time) {
                    $this->updatedSplits(is_scalar($time) ? (string) $time : null, ((int) $id).'.'.((int) $number));
                }
            }
        }

        $this->fillInputs();
        $this->announceOfflineRecord(array_filter($ticked));
        $this->dispatch('offline-synced');
    }

    /** Eén set bijwerken; geeft de set terug als hij nu (opnieuw) is afgevinkt */
    private function syncSet(SetLog $set, array $change): ?SetLog
    {
        foreach (['weight', 'reps', 'seconds'] as $field) {
            if (array_key_exists($field, $change) && (is_scalar($change[$field]) || $change[$field] === null)) {
                $this->updatedValues($change[$field], "{$set->id}.{$field}");
            }
        }

        $set->refresh();

        if (array_key_exists('warmup', $change)) {
            $set->update(['is_warmup' => (bool) $change['warmup']]);
        }

        if (array_key_exists('done', $change) && (bool) $change['done'] !== $set->is_completed) {
            $done = (bool) $change['done'];
            $set->update(['is_completed' => $done, 'completed_at' => $done ? $this->offlineTime($change['at'] ?? null) : null]);

            return $done ? $set : null;
        }

        return null;
    }

    private function syncCardio(SessionExercise $item, array $change): void
    {
        foreach (array_slice((array) ($change['fields'] ?? []), 0, 30, true) as $field => $value) {
            if (is_string($field) && (is_scalar($value) || $value === null)) {
                $this->updatedCardio($value, "{$item->id}.{$field}");
            }
        }

        if (array_key_exists('done', $change) && (bool) $change['done'] !== (bool) $item->completed_at) {
            $item->update(['completed_at' => $change['done'] ? $this->offlineTime($change['at'] ?? null) : null]);
        }
    }

    /** Het tijdstip waarop je afvinkte (van de telefoon), maar nooit voor de start of na nu */
    private function offlineTime(mixed $at): Carbon
    {
        $time = is_numeric($at) ? Carbon::createFromTimestampMs((int) $at)->setTimezone(now()->getTimezone()) : now();

        return $time->between($this->session->started_at, now()) ? $time : now();
    }

    /** Een nieuw record tussen de verstuurde sets: confetti en de melding, net als bij online afvinken */
    private function announceOfflineRecord(array $sets): void
    {
        foreach (array_reverse($sets) as $set) {
            $exercise = $set->sessionExercise->exercise;
            $records = ExerciseRecords::build($this->session, $exercise->id, $exercise->type);

            if ($records->isRecordSet($set)) {
                $this->dispatch('record', title: __('New record!'), text: $exercise->name.' · '.$records->describeSet($set));

                return;
            }
        }
    }

    /** Sets en oefeningen die intussen zijn weggehaald worden overgeslagen */
    private function offlineSet(int $id): ?SetLog
    {
        return SetLog::whereKey($id)
            ->whereHas('sessionExercise', fn ($q) => $q->where('workout_session_id', $this->session->id))
            ->with('sessionExercise.exercise')
            ->first();
    }

    private function offlineExercise(int $id): ?SessionExercise
    {
        return $this->session->exercises()->whereKey($id)->first();
    }
}
