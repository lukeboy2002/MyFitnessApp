<?php

namespace App\Actions;

use App\Models\SessionLike;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\FriendActivity;
use App\Support\Inbox;

/**
 * Duim geven of terugnemen op een gedeelde training van een vriend.
 * Alleen een nieuwe duim stuurt de eigenaar een melding. Geeft terug of je nu een duim geeft.
 */
class LikeTraining
{
    public function toggle(User $user, WorkoutSession $session): bool
    {
        abort_if($session->user_id === $user->id || ! FriendActivity::canSee($user, $session), 404);

        $like = SessionLike::where('workout_session_id', $session->id)->where('user_id', $user->id)->first();

        if ($like) {
            $like->delete();

            return false;
        }

        SessionLike::create(['workout_session_id' => $session->id, 'user_id' => $user->id]);

        $owner = $session->user;
        $locale = $owner->preferredLocale();
        Inbox::deliver($owner,
            title: __(':name gave you a thumbs up', ['name' => $user->name], $locale),
            body: __('for :name', ['name' => $session->name], $locale),
            url: route('buddies', ['tab' => 'activiteit'], absolute: false),
            tag: 'like-'.$session->id,
            push: $owner->push_buddies,
        );

        return true;
    }
}
