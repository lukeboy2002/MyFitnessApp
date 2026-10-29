<?php

namespace App\Actions;

use App\Models\BodyMeasurement;
use App\Models\BodyPart;
use App\Models\BodyWeight;
use App\Models\Exercise;
use App\Models\Muscle;
use App\Models\MuscleGroup;
use App\Models\ScheduledWorkout;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Verwijdert een account met alles wat erbij hoort: trainingen, workouts, planning, eigen oefeningen,
 * spieren, spiergroepen en lichaamsdelen, wegingen, maten, passkeys, sessies en alle plaatjes.
 * De standaardlijst en de gegevens van anderen blijven onaangeroerd.
 */
class DeleteUserAccount
{
    public function handle(User $user): void
    {
        $files = $this->files($user);

        // In vaste volgorde, zodat de restrict-sleutels (spier → spiergroep → lichaamsdeel) nooit in de weg zitten
        DB::transaction(function () use ($user) {
            WorkoutSession::where('user_id', $user->id)->delete();   // met oefeningen, sets, cardio en tussentijden
            ScheduledWorkout::where('user_id', $user->id)->delete();
            Workout::where('user_id', $user->id)->delete();          // met oefeningen en spiergroepen van de workout
            Exercise::where('user_id', $user->id)->delete();
            Muscle::where('user_id', $user->id)->delete();
            MuscleGroup::where('user_id', $user->id)->delete();
            BodyPart::where('user_id', $user->id)->delete();
            BodyWeight::where('user_id', $user->id)->delete();
            BodyMeasurement::where('user_id', $user->id)->delete();

            DB::table('passkeys')->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();   // ook ingelogd op andere apparaten
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            $user->delete();
        });

        // Pas na een geslaagde verwijdering de bestanden wissen
        Storage::disk('public')->delete($files);
    }

    /**
     * Profielfoto en plaatjes van eigen oefeningen, spiergroepen en lichaamsdelen.
     * Een plaatje dat ook bij een standaarditem of bij iemand anders hoort, blijft altijd staan.
     */
    private function files(User $user): array
    {
        $models = [Exercise::class, MuscleGroup::class, BodyPart::class];

        $own = collect($models)->flatMap(fn ($model) => $model::where('user_id', $user->id)->whereNotNull('image_path')->pluck('image_path'));
        $shared = collect($models)->flatMap(fn ($model) => $model::whereIn('image_path', $own)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $user->id))
            ->pluck('image_path'));

        return $own->diff($shared)->push($user->profile_photo_path)->filter()->unique()->values()->all();
    }
}
