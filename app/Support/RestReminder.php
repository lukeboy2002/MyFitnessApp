<?php

namespace App\Support;

use App\Jobs\SendRestOverPush;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Pushmelding aan het eind van de rust. Per gebruiker staat er hooguit één klaar:
 * een nieuwe rust, rust overslaan, pauze of afronden maakt de vorige ongeldig.
 */
class RestReminder
{
    public static function schedule(User $user, int $seconds, string $body, string $url): void
    {
        if (! WebPush::configured() || ! $user->pushSubscriptions()->exists()) {
            return;
        }

        $token = (string) Str::uuid();
        Cache::put(self::key($user->id), $token, now()->addSeconds($seconds + 600));

        SendRestOverPush::dispatch($user->id, $token, $body, $url)->delay(now()->addSeconds($seconds));
    }

    public static function cancel(User $user): void
    {
        Cache::forget(self::key($user->id));
    }

    /** Hoort de melding nog bij de laatste rust? Zo ja: meteen ongeldig maken, zodat hij maar één keer gaat */
    public static function claim(int $userId, string $token): bool
    {
        // Niet blind pullen: een oude melding mag de nieuwste niet weghalen
        if (Cache::get(self::key($userId)) !== $token) {
            return false;
        }

        Cache::forget(self::key($userId));

        return true;
    }

    private static function key(int $userId): string
    {
        return "rest-reminder:{$userId}";
    }
}
