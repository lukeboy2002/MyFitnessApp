<?php

namespace App\Actions;

use App\Models\SessionExercise;
use App\Models\UserMessage;
use App\Models\WorkoutSession;
use App\Support\Achievements;
use App\Support\ExerciseRecords;
use App\Support\Locale;

/**
 * Na het afronden van een training: nieuwe badges en records ook als bericht in Berichten,
 * zodat je ze later nog terugziet. Eén bericht per training, met een link naar de samenvatting.
 * Geen pushmelding: je zit op dat moment zelf in de app en ziet de samenvatting al.
 */
class PostTrainingHighlights
{
    public function handle(WorkoutSession $session): ?UserMessage
    {
        $session->loadMissing(['user', 'exercises.exercise', 'exercises.sets', 'exercises.cardio.splits']);

        $badges = Achievements::earnedIn($session);
        $records = $this->records($session);

        if ($badges->isEmpty() && $records === []) {
            return null;
        }

        $recordCount = array_sum(array_map('count', $records));
        $lines = [];

        if ($badges->isNotEmpty()) {
            $lines[] = trans_choice('New badge!|:count new badges!', $badges->count());
            foreach ($badges as $row) {
                $lines[] = '• '.$row['badge']->labelFor($row['level']).' ('.$row['badge']->name.' · '.$row['badge']->tierLabel($row['level']).')';
            }
        }

        if ($records !== []) {
            if ($lines) {
                $lines[] = '';
            }
            $lines[] = trans_choice(':count new record!|:count new records!', $recordCount);
            foreach ($records as $exercise => $items) {
                $lines[] = '• '.$exercise.': '.implode(', ', $items);
            }
        }

        return $session->user->messages()->create([
            'title' => $badges->isNotEmpty()
                ? trans_choice('New badge!|:count new badges!', $badges->count())
                : trans_choice(':count new record!|:count new records!', $recordCount),
            'body' => __('In your training :name', ['name' => $session->name])."\n\n".implode("\n", $lines),
            'url' => route('sessions.summary', $session, absolute: false),
        ]);
    }

    /**
     * Records per oefening, zoals op de samenvatting: verbeterde sets of cardio-records.
     *
     * @return array<string, list<string>> oefeningnaam => omschrijvingen
     */
    private function records(WorkoutSession $session): array
    {
        $history = ExerciseRecords::forSession($session);
        $records = [];

        foreach ($session->exercises as $item) {
            /** @var SessionExercise $item */
            $previous = $history[$item->exercise_id] ?? null;
            if (! $previous) {
                continue;
            }

            $found = $item->exercise->type->usesSets()
                ? $item->sets->filter(fn ($set) => $previous->isRecordSet($set))->map(fn ($set) => $previous->describeSet($set))->values()->all()
                : array_map(fn ($label) => Locale::lower($label), $previous->cardioRecords($item->cardio));

            if ($found !== []) {
                $records[$item->exercise->name] = [...($records[$item->exercise->name] ?? []), ...$found];
            }
        }

        return $records;
    }
}
