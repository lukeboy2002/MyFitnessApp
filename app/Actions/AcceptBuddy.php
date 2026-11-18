<?php

namespace App\Actions;

use App\Models\Buddy;
use App\Support\Inbox;

/** Uitnodiging accepteren; wie uitnodigde krijgt een bericht */
class AcceptBuddy
{
    public function handle(Buddy $buddy): Buddy
    {
        if ($buddy->isAccepted()) {
            return $buddy;
        }

        $buddy->update(['accepted_at' => now()]);

        $inviter = $buddy->user;
        Inbox::deliver($inviter,
            title: __('New buddy', [], $inviter->preferredLocale()),
            body: __(':name accepted your invitation. You can now share workouts.', ['name' => $buddy->buddy->name], $inviter->preferredLocale()),
            url: route('buddies', absolute: false),
            tag: 'buddy-'.$buddy->id,
            push: $inviter->push_buddies,
        );

        return $buddy;
    }
}
