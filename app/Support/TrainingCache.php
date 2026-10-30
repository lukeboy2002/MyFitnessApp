<?php

namespace App\Support;

use App\Enums\SessionStatus;
use App\Models\WorkoutSession;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Bewaart zware berekeningen over de afgeronde trainingen van een gebruiker (badges, records).
 * Bij elke uitkomst hoort een vingerafdruk van die trainingen: aantal, laatste id en laatste wijziging.
 * Rond je een training af, verwijder of importeer je er een, dan klopt de vingerafdruk niet meer
 * en wordt er één keer opnieuw gerekend. Handmatig legen is dus niet nodig.
 *
 * Alleen gewone waarden (arrays, getallen, tekst) bewaren: de cache pakt geen objecten uit.
 */
final class TrainingCache
{
    /** @param  array  $extra  wat de uitkomst verder bepaalt, bv. de drempels van de badges */
    public static function remember(int $userId, string $name, Closure $callback, array $extra = []): mixed
    {
        $key = "training:{$userId}:{$name}";
        $fingerprint = self::fingerprint($userId, $extra);
        $cached = Cache::get($key);

        if (is_array($cached) && ($cached['fingerprint'] ?? null) === $fingerprint) {
            return $cached['value'];
        }

        $value = $callback();
        Cache::put($key, ['fingerprint' => $fingerprint, 'value' => $value], now()->addMonth());

        return $value;
    }

    private static function fingerprint(int $userId, array $extra): string
    {
        $sessions = WorkoutSession::query()
            ->where('user_id', $userId)
            ->where('status', SessionStatus::Completed)
            ->toBase()
            ->selectRaw('count(*) as total, max(id) as last_id, max(updated_at) as changed_at')
            ->first();

        return md5(json_encode([(array) $sessions, $extra]));
    }
}
