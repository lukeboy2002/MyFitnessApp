<?php

namespace App\Actions;

use App\Enums\SessionStatus;
use App\Models\ScheduledWorkout;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Start een training vanuit een workout (de oefeningen en doelen worden gekopieerd),
 * of een vrije training via Quickstart (leeg, oefeningen voeg je tijdens de training toe).
 * Er loopt maar één training tegelijk; loopt er al een, dan krijg je die terug.
 * Het starten gebeurt per gebruiker één voor één, zodat dubbel tikken geen twee trainingen maakt.
 */
class StartWorkoutSession
{
    public function __construct(private AddSessionExercise $addExercise) {}

    public function handle(User $user, Workout $workout): WorkoutSession
    {
        return $this->exclusive($user, function () use ($user, $workout) {
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

            // Stond deze workout voor vandaag gepland? Dan is die nu gedaan
            ScheduledWorkout::where('user_id', $user->id)->where('workout_id', $workout->id)
                ->whereDate('scheduled_on', today())->open()->orderBy('id')->first()
                ?->update(['workout_session_id' => $session->id]);

            return $session;
        });
    }

    /** Quickstart: een vrije training, altijd met dezelfde naam en kleur */
    public function quickstart(User $user): WorkoutSession
    {
        return $this->exclusive($user, fn () => WorkoutSession::create([
            'user_id' => $user->id,
            'name' => WorkoutSession::QUICKSTART_NAME,
            'color' => WorkoutSession::QUICKSTART_COLOR,
            'status' => SessionStatus::Active,
            'started_at' => now(),
            'source' => WorkoutSession::QUICKSTART,
        ]));
    }

    /**
     * Vergrendelt de rij van de gebruiker tot de training is aangemaakt. Een tweede tik wacht
     * daardoor op de eerste en vindt dan de lopende training, in plaats van een nieuwe te maken.
     *
     * @param  Closure(): WorkoutSession  $create
     */
    private function exclusive(User $user, Closure $create): WorkoutSession
    {
        return DB::transaction(function () use ($user, $create) {
            User::whereKey($user->id)->lockForUpdate()->first();

            return WorkoutSession::where('user_id', $user->id)->running()->first() ?? $create();
        });
    }
}
