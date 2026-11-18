<?php

namespace App\Actions;

use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\SharedWorkout;
use App\Models\User;
use App\Models\Workout;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Gedeelde workout overnemen: een eigen kopie zoals de workout nu is.
 * Eigen oefeningen van de afzender gaan als kopie mee (met standaardspiergroepen en -materiaal);
 * heb je al een eigen oefening met dezelfde naam, dan wordt die gebruikt.
 */
class AcceptSharedWorkout
{
    public function handle(SharedWorkout $share): Workout
    {
        $recipient = $share->recipient;
        $workout = $share->workout()->with(['exercises.exercise.muscleGroups', 'exercises.exercise.equipment', 'muscleGroups'])->firstOrFail();

        return DB::transaction(function () use ($share, $recipient, $workout) {
            $copy = $workout->replicate(['slug', 'archived_at', 'source_workout_id', 'user_id']);
            $copy->user_id = $recipient->id;
            $copy->slug = Workout::uniqueSlug($workout->name, $recipient->id);
            $copy->save();

            $copy->muscleGroups()->sync($workout->muscleGroups->whereNull('user_id')->pluck('id'));

            foreach ($workout->exercises as $item) {
                $copy->exercises()->create([
                    ...Arr::except($item->only($item->getFillable()), ['workout_id', 'exercise_id']),
                    'exercise_id' => $this->exerciseFor($item->exercise, $recipient)->id,
                ]);
            }

            $share->update(['accepted_at' => now(), 'copy_workout_id' => $copy->id]);

            return $copy;
        });
    }

    private function exerciseFor(Exercise $exercise, User $recipient): Exercise
    {
        if ($exercise->isStandard() || $exercise->user_id === $recipient->id) {
            return $exercise;
        }

        $existing = Exercise::where('user_id', $recipient->id)->whereRaw('lower(name) = ?', [mb_strtolower($exercise->name)])->first();
        if ($existing) {
            return $existing;
        }

        $copy = $exercise->replicate(['slug', 'archived_at', 'user_id', 'image_path', 'equipment_id']);
        $copy->user_id = $recipient->id;
        $copy->slug = Exercise::uniqueSlug($exercise->name, $recipient->id);
        $copy->equipment_id = $exercise->equipment?->isStandard() ? $exercise->equipment_id : Equipment::standardId('other');
        $copy->image_path = $this->copyImage($exercise->image_path);
        $copy->save();

        // Alleen standaardspiergroepen; eigen spiergroepen van de afzender heeft de ontvanger niet
        $copy->muscleGroups()->sync(
            $exercise->muscleGroups->whereNull('user_id')->mapWithKeys(fn ($group) => [$group->id => ['is_primary' => $group->pivot->is_primary]])->all()
        );

        return $copy;
    }

    private function copyImage(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $new = dirname($path).'/'.uniqid('shared-').'.'.pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk('public')->copy($path, $new) ? $new : null;
    }
}
