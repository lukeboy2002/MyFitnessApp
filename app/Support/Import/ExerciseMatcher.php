<?php

namespace App\Support\Import;

use App\Models\DataImport;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Zoekt bij een oefeningnaam uit een andere app de oefening in deze app.
 * Zeker (vanzelf gekoppeld): dezelfde naam, dezelfde woorden in een andere volgorde,
 * of zoals je hem bij een eerdere import zelf koppelde. Anders een voorstel als de woorden genoeg overlappen.
 */
final class ExerciseMatcher
{
    private const MIN_OVERLAP = 0.5;

    /** @var Collection<int, Exercise> */
    private Collection $exercises;

    /** @var array<string, int> naam => exercise_id uit eerdere imports */
    private array $remembered = [];

    public function __construct(private User $user)
    {
        $this->exercises = Exercise::visibleTo($user)->active()
            ->orderByRaw('user_id is null') // eigen oefeningen eerst
            ->get(['id', 'user_id', 'name', 'slug', 'type']);

        foreach (DataImport::where('user_id', $user->id)->oldest()->pluck('exercise_map') as $map) {
            foreach ($map ?? [] as $name => $id) {
                if ($id) {
                    $this->remembered[self::normalize($name)] = (int) $id;
                }
            }
        }
    }

    /** @return array{id: ?int, sure: bool} */
    public function match(string $name): array
    {
        $normal = self::normalize($name);

        if (($id = $this->remembered[$normal] ?? null) && $this->exercises->contains('id', $id)) {
            return ['id' => $id, 'sure' => true];
        }

        $slug = str($name)->slug()->value();
        $tokens = self::tokens($name);
        $best = null;
        $bestScore = 0.0;

        foreach ($this->exercises as $exercise) {
            if (self::normalize($exercise->name) === $normal || $exercise->slug === $slug) {
                return ['id' => $exercise->id, 'sure' => true];
            }

            $other = self::tokens($exercise->name);
            $common = count(array_intersect($tokens, $other));
            $score = $common / max(1, count(array_unique([...$tokens, ...$other])));
            if ($score >= 1) {
                return ['id' => $exercise->id, 'sure' => true];
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$exercise->id, $score];
            }
        }

        return ['id' => $bestScore >= self::MIN_OVERLAP ? $best : null, 'sure' => false];
    }

    private static function normalize(string $name): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(str_replace('&', ' and ', $name))));
    }

    /** @return list<string> woorden zonder meervoud-s, zonder kleine woordjes */
    private static function tokens(string $name): array
    {
        $words = array_filter(explode(' ', self::normalize($name)), fn ($word) => ! in_array($word, ['', 'the', 'and', 'with', 'a', 'on'], true));

        return array_values(array_unique(array_map(fn ($word) => strlen($word) > 3 ? rtrim($word, 's') : $word, $words)));
    }
}
