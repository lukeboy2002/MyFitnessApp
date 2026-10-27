<?php

namespace App\Support;

use App\Enums\ExerciseType;
use App\Enums\ExperienceLevel;
use App\Enums\FitnessGoal;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Een eerste workout op basis van je doel (welkomstscherm), met de standaard gym-machines.
 * Beginners doen een set minder, gevorderden een set meer (2 tot 5 sets). Gewichten vul je tijdens je eerste training in.
 */
class StarterWorkout
{
    /**
     * doel => [naam, kleur, omschrijving, rust in sec, [slug => [sets, reps] of cardio [minuten]]]
     */
    public const TEMPLATES = [
        'lose_weight' => ['Vetverbranding', '#db3f6b', 'Kracht voor het hele lichaam met korte rust, afgesloten met rustige cardio.', 60, [
            'leg-press-machine' => [3, 15],
            'chest-press-machine' => [3, 15],
            'seated-row' => [3, 15],
            'shoulder-press' => [3, 15],
            'ab-crush-machine' => [3, 15],
            'treadmill' => [20],
        ]],
        'build_muscle' => ['Full body opbouw', '#f97316', 'Elke grote spiergroep, met 8 tot 12 herhalingen. Doe hem twee of drie keer per week.', 90, [
            'leg-press-machine' => [4, 10],
            'chest-press-machine' => [4, 10],
            'lat-pull-down-cable-station' => [4, 10],
            'shoulder-press' => [3, 10],
            'seated-leg-curl' => [3, 12],
            'bilateral-arm-curl-machine' => [3, 12],
            'seated-triceps-press' => [3, 12],
        ]],
        'strength' => ['Kracht basis', '#1d63c5', 'Zware sets met weinig herhalingen en lange rust.', 180, [
            'leg-press-machine' => [5, 5],
            'chest-press-machine' => [5, 5],
            'seated-row' => [5, 5],
            'shoulder-press' => [4, 6],
            'back-extension-machine' => [3, 10],
        ]],
        'endurance' => ['Conditie', '#0e9f8a', 'Vooral cardio, met een paar oefeningen voor de grote spieren.', 60, [
            'rowing' => [10],
            'leg-press-machine' => [2, 15],
            'seated-row' => [2, 15],
            'ab-crush-machine' => [2, 15],
            'treadmill' => [20],
        ]],
        'stay_fit' => ['Fit en gezond', '#8b5cf6', 'Een korte training voor het hele lichaam, met wat cardio.', 75, [
            'cross-trainer' => [10],
            'leg-press-machine' => [3, 12],
            'chest-press-machine' => [3, 12],
            'lat-pull-down-cable-station' => [3, 12],
            'shoulder-press' => [3, 12],
            'ab-crush-machine' => [3, 12],
        ]],
    ];

    public function __construct(
        public string $name,
        public string $color,
        public string $description,
        public int $rest,
        /** @var Collection<int, array{exercise: Exercise, sets: ?int, reps: ?int, minutes: ?int}> */
        public Collection $items,
    ) {}

    public static function for(FitnessGoal $goal, ?ExperienceLevel $experience = null): self
    {
        [$name, $color, $description, $rest, $targets] = self::TEMPLATES[$goal->value];
        $extraSets = match ($experience) {
            ExperienceLevel::Beginner => -1,
            ExperienceLevel::Advanced => 1,
            default => 0,
        };

        $exercises = Exercise::whereNull('user_id')->whereIn('slug', array_keys($targets))->get()->keyBy('slug');

        $items = collect($targets)
            ->filter(fn ($target, $slug) => $exercises->has($slug))
            ->map(function ($target, $slug) use ($exercises, $extraSets) {
                $exercise = $exercises[$slug];
                $cardio = $exercise->type === ExerciseType::Cardio;

                return [
                    'exercise' => $exercise,
                    'sets' => $cardio ? null : max(2, min(5, $target[0] + $extraSets)),
                    'reps' => $cardio ? null : $target[1],
                    'minutes' => $cardio ? $target[0] : null,
                ];
            })
            ->values();

        return new self($name, $color, $description, $rest, $items);
    }

    /** Als workout opslaan voor deze gebruiker */
    public function createFor(User $user): Workout
    {
        return DB::transaction(function () use ($user) {
            $workout = Workout::create([
                'user_id' => $user->id,
                'name' => $this->name,
                'slug' => Workout::uniqueSlug($this->name, $user->id),
                'color' => $this->color,
                'description' => $this->description,
            ]);

            foreach ($this->items as $position => $item) {
                $workout->exercises()->create([
                    ...WorkoutExercise::defaultsFor($item['exercise'], $user),
                    'exercise_id' => $item['exercise']->id,
                    'position' => $position,
                    ...($item['minutes']
                        ? ['target_duration_seconds' => $item['minutes'] * 60]
                        : ['target_sets' => $item['sets'], 'target_reps' => $item['reps'], 'target_weight' => null, 'rest_seconds' => $user->default_rest_seconds ?? $this->rest]),
                ]);
            }

            $workout->load('exercises.exercise.muscleGroups');
            $workout->muscleGroups()->sync($workout->exercises->map(fn ($item) => $item->exercise->primaryMuscleGroup()?->id)->filter()->unique());

            return $workout;
        });
    }
}
