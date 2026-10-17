<?php

namespace App\Actions;

use App\Enums\SessionStatus;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\DB;

/**
 * Start een training vanuit een workout (de oefeningen en doelen worden gekopieerd),
 * of een vrije training via Quickstart (leeg, oefeningen voeg je tijdens de training toe).
 * Er loopt maar één training tegelijk; loopt er al een, dan krijg je die terug.
 */
class StartWorkoutSession
{
    public function __construct(private AddSessionExercise $addExercise) {}

    public function handle(User $user, Workout $workout): WorkoutSession
    {
        if ($running = $this->running($user)) {
            return $running;
        }

        return DB::transaction(function () use ($user, $workout) {
            $session = WorkoutSession::create([
                'user_id' => $user->id,
                'workout_id' => $workout->id,
                'name' => $workout->name,
                'color' => $workout->color,
                'status' => SessionStatus::Active,
                'started_at' => now(),
            ]);

            $workout->exercises()->with('exercise')->get()->each(
                fn (WorkoutExercise $item) => $this->addExercise->handle($session, $item->exercise, $item->toArray(), $item->id)
            );

            return $session;
        });
    }

    /** Quickstart: een vrije training, altijd met dezelfde naam en kleur */
    public function quickstart(User $user): WorkoutSession
    {
        return $this->running($user) ?? WorkoutSession::create([
            'user_id' => $user->id,
            'name' => WorkoutSession::QUICKSTART_NAME,
            'color' => WorkoutSession::QUICKSTART_COLOR,
            'status' => SessionStatus::Active,
            'started_at' => now(),
            'source' => WorkoutSession::QUICKSTART,
        ]);
    }

    private function running(User $user): ?WorkoutSession
    {
        return WorkoutSession::where('user_id', $user->id)->running()->first();
    }
}
