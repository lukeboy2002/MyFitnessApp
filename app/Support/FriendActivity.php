<?php

namespace App\Support;

use App\Models\Buddy;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Gedeelde trainingen (workout_sessions.shared_at) van jou en je vrienden: tab Activiteit op de pagina Vrienden.
 * Een vriend ziet je training alleen zolang jullie gekoppeld zijn en jouw profiel openbaar is.
 */
class FriendActivity
{
    /** Hoeveel trainingen de tab toont */
    public const LIMIT = 30;

    /** Ids van je vrienden met een openbaar profiel */
    public static function friendIds(User $user): Collection
    {
        $ids = Buddy::accepted()->involving($user)->get(['user_id', 'buddy_id'])
            ->map(fn (Buddy $row) => $row->user_id === $user->id ? $row->buddy_id : $row->user_id);

        return User::whereKey($ids)->publicProfile()->notBanned()->pluck('id');
    }

    /** Wat $viewer mag zien: eigen gedeelde trainingen en die van vrienden */
    public static function query(User $viewer): Builder
    {
        return WorkoutSession::query()
            ->whereNotNull('shared_at')
            ->whereIn('user_id', self::friendIds($viewer)->push($viewer->id));
    }

    public static function feed(User $viewer, int $limit = self::LIMIT): Collection
    {
        return self::query($viewer)
            ->with(['user', 'likes.user'])
            ->latest('ended_at')
            ->limit($limit)
            ->get();
    }

    public static function canSee(User $viewer, WorkoutSession $session): bool
    {
        return self::query($viewer)->whereKey($session->id)->exists();
    }
}
