<?php

namespace App\Support;

/**
 * Supersets: paren van twee opeenvolgende oefeningen. Een oefening met superset_next is gekoppeld
 * aan de volgende. Cardio doet niet mee en een oefening zit in hooguit één paar.
 * Werkt op een lijst sleutels in volgorde, zodat de workout-editor en het trainingsscherm dezelfde regels volgen.
 */
final class Supersets
{
    /**
     * Alleen geldige koppelingen houden.
     *
     * @param  list<int|string>  $order  sleutels in volgorde
     * @param  array<int|string, bool>  $linked  gekoppeld aan de volgende?
     * @param  array<int|string, bool>  $cardio  is cardio?
     * @return array<int|string, bool>
     */
    public static function normalize(array $order, array $linked, array $cardio): array
    {
        $result = [];
        $previous = false;

        foreach ($order as $i => $key) {
            $next = $order[$i + 1] ?? null;
            $result[$key] = $previous = ! $previous && ($linked[$key] ?? false) && $next !== null
                && ! ($cardio[$key] ?? false) && ! ($cardio[$next] ?? false);
        }

        return $result;
    }

    /** Kan $key aan de volgende gekoppeld worden (beide geen cardio en geen van beide al in een paar)? */
    public static function canLink(array $order, array $linked, array $cardio, int|string $key): bool
    {
        $i = array_search($key, $order, true);
        $next = $i === false ? null : ($order[$i + 1] ?? null);

        return $next !== null && ! ($cardio[$key] ?? false) && ! ($cardio[$next] ?? false)
            && ! ($i > 0 && ($linked[$order[$i - 1]] ?? false)) && ! ($linked[$next] ?? false);
    }

    /** De sleutels van het paar waar $key in zit (of alleen $key), in volgorde */
    public static function block(array $order, array $linked, int|string $key): array
    {
        $i = array_search($key, $order, true);

        return match (true) {
            $i === false => [$key],
            ($linked[$key] ?? false) && isset($order[$i + 1]) => [$key, $order[$i + 1]],
            $i > 0 && ($linked[$order[$i - 1]] ?? false) => [$order[$i - 1], $key],
            default => [$key],
        };
    }

    /**
     * $key naar plek $position slepen (plek in de lijst zonder $key, zoals wire:sort die geeft).
     * Een paar gaat altijd samen mee en wordt nooit door een andere oefening gesplitst.
     */
    public static function move(array $order, array $linked, int|string $key, int $position): array
    {
        $block = self::block($order, $linked, $key);
        $without = array_values(array_filter($order, fn ($k) => $k !== $key));
        $position = max(0, min($position, count($without)));
        $position -= count(array_filter(array_slice($without, 0, $position), fn ($k) => in_array($k, $block, true)));

        $rest = array_values(array_filter($order, fn ($k) => ! in_array($k, $block, true)));
        if ($position > 0 && $position < count($rest) && ($linked[$rest[$position - 1]] ?? false)) {
            $position++;
        }

        array_splice($rest, $position, 0, $block);

        return $rest;
    }
}
