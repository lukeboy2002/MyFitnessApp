<?php

namespace App\Actions;

use App\Enums\ExerciseType;
use App\Models\SessionExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\FriendActivity;
use App\Support\Inbox;

/**
 * Een afgeronde training wel of niet met je vrienden delen (samenvatting na Afronden).
 * Vrienden zien alleen shared_summary: duur, sets, volume, afstand en nieuwe records; geen notitie, geen losse sets.
 * Alleen de eerste keer delen stuurt je vrienden een melding. Geeft het aantal vrienden met een melding terug.
 */
class ShareTraining
{
    public function __construct(private PostTrainingHighlights $highlights) {}

    public function share(WorkoutSession $session): int
    {
        $first = $session->shared_summary === null;
        $session->loadMissing(['user', 'exercises.exercise', 'exercises.sets', 'exercises.cardio.splits']);

        WorkoutSession::withoutTimestamps(fn () => $session->update([
            'shared_at' => now(),
            'shared_summary' => $this->summary($session),
        ]));

        if (! $first) {
            return 0;
        }

        $friends = User::whereKey(FriendActivity::friendIds($session->user))->get();
        $records = count($session->shared_summary['records']);

        foreach ($friends as $friend) {
            $locale = $friend->preferredLocale();
            Inbox::deliver($friend,
                title: __(':name shared a training', ['name' => $session->user->name], $locale),
                body: $session->name.($records ? ' · '.trans_choice(':count new record!|:count new records!', $records, [], $locale) : ''),
                url: route('buddies', ['tab' => 'activiteit'], absolute: false),
                tag: 'training-'.$session->id,
                push: $friend->push_buddies,
            );
        }

        return $friends->count();
    }

    /** Niet meer delen: vrienden zien hem niet meer (de duimen blijven bewaard) */
    public function unshare(WorkoutSession $session): void
    {
        WorkoutSession::withoutTimestamps(fn () => $session->update(['shared_at' => null]));
    }

    /** @return array{seconds: int, sets: int, volume: float, distance_m: int, records: list<string>} */
    private function summary(WorkoutSession $session): array
    {
        $records = [];
        foreach ($this->highlights->records($session) as $exercise => $items) {
            $records[] = $exercise.': '.implode(', ', $items);
        }

        return [
            'seconds' => $session->elapsedSeconds(),
            'sets' => $session->exercises->flatMap->sets->where('is_completed', true)->count(),
            'volume' => $session->volume(),
            'distance_m' => (int) $session->exercises->sum(fn (SessionExercise $item) => $item->exercise->type === ExerciseType::Cardio ? ($item->cardio?->distance_m ?? 0) : 0),
            'records' => $records,
        ];
    }
}
