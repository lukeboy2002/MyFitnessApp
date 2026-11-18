<?php

namespace App\Actions;

use App\Models\Buddy;
use App\Models\SharedWorkout;
use App\Models\User;
use App\Models\Workout;
use App\Support\Inbox;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Workout naar maatjes met een openbaar profiel sturen; zij nemen hem over (kopie) of weigeren. Geeft het aantal ontvangers terug */
class ShareWorkout
{
    /** @param  Collection<int, User>  $recipients */
    public function handle(User $sender, Workout $workout, Collection $recipients, ?string $message = null): int
    {
        $message = filled($message) ? Str::limit(trim($message), 300, '') : null;
        $sent = 0;

        foreach ($recipients as $recipient) {
            if (! $recipient->profile_public || ! Buddy::areBuddies($sender, $recipient)) {
                continue;
            }

            // Staat dezelfde workout nog open bij deze ontvanger? Dan alleen het bericht bijwerken
            $share = SharedWorkout::open()->where('workout_id', $workout->id)->where('recipient_id', $recipient->id)->first();
            $share
                ? $share->update(['message' => $message])
                : $share = SharedWorkout::create(['workout_id' => $workout->id, 'sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'message' => $message]);

            $locale = $recipient->preferredLocale();
            Inbox::deliver($recipient,
                title: __(':name shared a workout', ['name' => $sender->name], $locale),
                body: $workout->name.($message ? ' · “'.Str::limit($message, 240).'”' : ''),
                url: route('workouts.index', ['ontvangen' => 1], absolute: false),
                tag: 'shared-'.$share->id,
                push: $recipient->push_buddies,
            );
            $sent++;
        }

        return $sent;
    }
}
