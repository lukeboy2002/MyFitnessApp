<?php

namespace App\Support;

use App\Enums\SessionStatus;
use App\Models\RoutineItem;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Support\Collection;

/**
 * Schema: je workouts in een vaste volgorde (A-B-C-D, herhalend).
 * users.next_routine_item_id = wie aan de beurt is; leeg of weg = de eerste.
 * Gearchiveerde of lege workouts worden overgeslagen.
 * Afronden van de workout die aan de beurt is schuift het schema door (Sessions\Run::finish).
 */
class Routine
{
    /** Alle plekken op volgorde, met workout (ook gearchiveerde, voor de instelpagina) */
    public static function items(User $user): Collection
    {
        return $user->routineItems()->with(['workout' => fn ($q) => $q->withCount('exercises')])->get();
    }

    /** Alleen plekken die je kunt starten */
    public static function playable(User $user): Collection
    {
        return self::items($user)
            ->filter(fn (RoutineItem $item) => ! $item->workout->isArchived() && $item->workout->exercises_count > 0)
            ->values();
    }

    /** Wie aan de beurt is; null = geen (startbaar) schema */
    public static function current(User $user): ?RoutineItem
    {
        $all = self::items($user);
        $playable = self::playable($user);

        if ($playable->isEmpty()) {
            return null;
        }

        $pointer = $all->firstWhere('id', $user->next_routine_item_id);
        if (! $pointer) {
            return $playable->first();
        }

        // Is de aangewezen plek niet startbaar? Dan de eerstvolgende die het wel is
        $index = $all->search(fn (RoutineItem $item) => $item->is($pointer));
        foreach ($all->slice($index)->concat($all->slice(0, $index)) as $item) {
            if ($playable->contains('id', $item->id)) {
                return $playable->firstWhere('id', $item->id);
            }
        }

        return $playable->first();
    }

    /** De plek na $item (rond: na de laatste weer de eerste) */
    public static function after(User $user, RoutineItem $item): ?RoutineItem
    {
        $playable = self::playable($user);
        $index = $playable->search(fn (RoutineItem $row) => $row->is($item));

        return $index === false ? $playable->first() : $playable->get(($index + 1) % $playable->count());
    }

    /** Eén plek verder (Overslaan) */
    public static function advance(User $user): ?RoutineItem
    {
        $current = self::current($user);
        $next = $current ? self::after($user, $current) : null;
        $user->forceFill(['next_routine_item_id' => $next?->id])->save();

        return $next;
    }

    /** Zelf kiezen wie de volgende is */
    public static function choose(User $user, int $itemId): void
    {
        $item = self::playable($user)->firstWhere('id', $itemId);

        if ($item) {
            $user->forceFill(['next_routine_item_id' => $item->id])->save();
        }
    }

    /** Training afgerond: was dit de workout die aan de beurt was, dan schuift het schema door */
    public static function completed(WorkoutSession $session): void
    {
        $user = $session->user;
        $current = $session->workout_id ? self::current($user) : null;

        if ($current && $current->workout_id === $session->workout_id) {
            $user->forceFill(['next_routine_item_id' => self::after($user, $current)?->id])->save();
        }
    }

    /** Vandaag al een training afgerond? Dan toont het dashboard "Volgende keer" */
    public static function trainedToday(User $user): bool
    {
        return WorkoutSession::where('user_id', $user->id)
            ->where('status', SessionStatus::Completed)
            ->whereDate('ended_at', today())
            ->exists();
    }
}
