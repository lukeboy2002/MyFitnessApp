<?php

namespace App\Actions;

use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Models\DataImport;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\Import\WorkoutFile;
use Illuminate\Support\Facades\DB;

/**
 * Zet de trainingen uit een export van Hevy, Strong of FitNotes in je geschiedenis.
 * Een training die er al in staat (zelfde app, datum en naam) wordt overgeslagen, net als oefeningen die je niet koppelde.
 * Geïmporteerde trainingen zijn afgerond en privé; badges en records rekenen ze vanzelf mee.
 */
class ImportWorkoutHistory
{
    /** @param  array<string, int>  $map  naam in het bestand => exercise_id (0 = overslaan) */
    public function run(User $user, WorkoutFile $file, array $map, string $filename, bool $imperial = false): DataImport
    {
        $exercises = Exercise::visibleTo($user)->whereKey(array_filter($map))->get()->keyBy('id');

        return DB::transaction(function () use ($user, $file, $map, $filename, $imperial, $exercises) {
            $import = DataImport::create([
                'user_id' => $user->id,
                'app' => $file->app,
                'filename' => mb_substr($filename, 0, 255),
                'exercise_map' => $map,
            ]);

            $existing = WorkoutSession::where('user_id', $user->id)->where('external_id', 'like', $file->app.':%')->pluck('external_id')->flip();
            $created = $skipped = 0;

            foreach ($file->sessions($imperial) as $data) {
                $externalId = self::externalId($file->app, $data);
                $items = collect($data['exercises'])
                    ->map(fn ($item) => [...$item, 'exercise' => $exercises->get($map[$item['name']] ?? 0)])
                    ->filter(fn ($item) => $item['exercise'] !== null);

                if ($existing->has($externalId) || $items->isEmpty()) {
                    $skipped++;

                    continue;
                }
                $existing->put($externalId, true);

                $session = WorkoutSession::create([
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'color' => WorkoutSession::IMPORT_COLOR,
                    'status' => SessionStatus::Completed,
                    'started_at' => $data['started_at'],
                    'ended_at' => $data['ended_at'],
                    'duration_seconds' => $data['duration'] ?? 0,
                    'source' => $file->app,
                    'external_id' => $externalId,
                    'data_import_id' => $import->id,
                    'notes' => $data['notes'],
                ]);

                $position = 0;
                foreach ($items as $item) {
                    $position += (int) $this->addExercise($session, $item['exercise'], $item, $position);
                }
                $created++;
            }

            $import->update(['sessions_count' => $created, 'skipped_count' => $skipped]);

            return $import;
        });
    }

    /** Vaste sleutel per training uit de andere app, zodat dezelfde training nooit twee keer wordt geïmporteerd */
    public static function externalId(string $app, array $session): string
    {
        return $app.':'.md5($session['key'].'|'.$session['name']);
    }

    /** Ongedaan maken: de import en al zijn trainingen weg */
    public function undo(DataImport $import): void
    {
        DB::transaction(function () use ($import) {
            $import->sessions()->delete();
            $import->delete();
        });
    }

    private function addExercise(WorkoutSession $session, Exercise $exercise, array $item, int $position): bool
    {
        $sets = collect($item['sets']);

        if ($exercise->type === ExerciseType::Cardio) {
            $seconds = (int) $sets->sum('seconds');
            $distance = (int) $sets->sum('distance_m');
            if (! $seconds && ! $distance) {
                return false;
            }

            $session->exercises()->create([
                'exercise_id' => $exercise->id,
                'position' => $position,
                'target_duration_seconds' => $seconds ?: null,
                'completed_at' => $session->ended_at,
                'notes' => $item['notes'],
            ])->cardio()->create([
                'duration_seconds' => $seconds ?: null,
                'distance_m' => $distance ?: null,
                'source' => $session->source,
            ]);

            return true;
        }

        $sets = $sets->map(fn ($set) => [
            ...$set,
            'weight' => $exercise->type === ExerciseType::Duration || ! $set['weight'] ? null : $set['weight'],
            'seconds' => $exercise->type === ExerciseType::Duration ? $set['seconds'] : null,
        ])->filter(fn ($set) => $set['reps'] || $set['weight'] || $set['seconds'])->values();

        if ($sets->isEmpty()) {
            return false;
        }

        $work = $sets->reject(fn ($set) => $set['warmup'])->first() ?? $sets->first();
        $sessionExercise = $session->exercises()->create([
            'exercise_id' => $exercise->id,
            'position' => $position,
            'target_sets' => min(255, $sets->reject(fn ($set) => $set['warmup'])->count() ?: $sets->count()),
            'target_reps' => $work['reps'],
            'target_weight' => $work['weight'],
            'target_duration_seconds' => $work['seconds'],
            'completed_at' => $session->ended_at,
            'notes' => $item['notes'],
        ]);

        foreach ($sets as $number => $set) {
            $sessionExercise->sets()->create([
                'set_number' => $number + 1,
                'is_warmup' => $set['warmup'],
                'target_reps' => $set['reps'],
                'target_weight' => $set['weight'],
                'target_duration_seconds' => $set['seconds'],
                'reps' => $set['reps'],
                'weight' => $set['weight'],
                'duration_seconds' => $set['seconds'],
                'is_completed' => true,
                'completed_at' => $session->ended_at,
                'rpe' => $set['rpe'],
            ]);
        }

        return true;
    }
}
