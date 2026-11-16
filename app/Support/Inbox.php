<?php

namespace App\Support;

use App\Models\User;
use App\Models\UserMessage;
use Illuminate\Support\Str;

/**
 * Een bericht in Berichten van één gebruiker, en (als $push) dezelfde tekst als pushmelding.
 * Tikken op de pushmelding markeert het bericht als gelezen en opent daarna de link van het bericht.
 * Voor herinneringen, weekoverzicht, niet-actief en antwoorden via Contact.
 */
class Inbox
{
    public static function deliver(User $user, string $title, string $body, ?string $url = null, ?string $tag = null, bool $push = true): UserMessage
    {
        $message = $user->messages()->create(['title' => $title, 'body' => $body, 'url' => $url]);

        if ($push) {
            WebPush::send($user, array_filter([
                'title' => $title,
                'body' => Str::limit($body, 200),
                'url' => route('messages', ['read' => $message->id, 'go' => 1], absolute: false),
                'tag' => $tag,
            ]));
        }

        return $message;
    }
}
