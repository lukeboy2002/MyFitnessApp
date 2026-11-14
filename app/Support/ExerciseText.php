<?php

namespace App\Support;

use App\Models\Exercise;

/**
 * Omschrijving, uitvoering en tips van een oefening in de taal van de gebruiker.
 * Alleen standaardoefeningen (user_id null) hebben vertalingen, in lang/{taal}/exercises.php per slug.
 * Heeft een admin de Engelse tekst aangepast (hash klopt niet meer), dan tonen we de tekst uit de database.
 */
class ExerciseText
{
    /** @return array{description: ?string, instructions: list<string>, tips: list<string>} */
    public static function for(Exercise $exercise): array
    {
        $original = [
            'description' => $exercise->description,
            'instructions' => $exercise->instructions ?? [],
            'tips' => $exercise->tips ?? [],
        ];

        if ($exercise->user_id !== null || $exercise->slug === null) {
            return $original;
        }

        $key = 'exercises.'.$exercise->slug;
        $translation = trans($key);

        if (! is_array($translation) || ($translation['hash'] ?? null) !== self::hash($exercise)) {
            return $original;
        }

        return [
            'description' => $translation['description'] ?? $original['description'],
            'instructions' => $translation['instructions'] ?? $original['instructions'],
            'tips' => $translation['tips'] ?? $original['tips'],
        ];
    }

    /** Vingerafdruk van de Engelse tekst waarop de vertaling is gebaseerd */
    public static function hash(Exercise $exercise): string
    {
        return substr(md5(json_encode([$exercise->description, $exercise->instructions ?? [], $exercise->tips ?? []])), 0, 10);
    }
}
