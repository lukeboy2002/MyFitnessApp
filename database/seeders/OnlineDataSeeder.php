<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Lokaal testen met de echte gegevens van de online app.
 * Bron: database/seeders/data/online.json (staat niet in git, gemaakt uit een dump van de online database).
 * Het account krijgt lokaal het wachtwoord "password". Workouts en trainingen van dat account worden eerst leeggemaakt.
 * Draait niet in productie.
 */
class OnlineDataSeeder extends Seeder
{
    public const FILE = 'seeders/data/online.json';

    /** Oude id => nieuwe id per tabel */
    private array $ids = [];

    /** Kolommen per tabel; kolommen die lokaal niet (meer) bestaan worden overgeslagen */
    private array $columns = [];

    public function run(): void
    {
        if (app()->isProduction() || ! is_file($path = database_path(self::FILE))) {
            return;
        }

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($data) {
            $this->ids['muscle_groups'] = $this->bySlug($data['muscle_groups'], MuscleGroup::whereNull('user_id')->pluck('id', 'slug'));

            foreach ($data['users'] as $row) {
                $user = User::updateOrCreate(['email' => $row['email']], [
                    ...Arr::only($row, ['name', 'username', 'weekly_goal_minutes']),
                    'password' => Hash::make('password'),
                ]);
                $user->forceFill(Arr::only($row, ['email_verified_at', 'created_at']))->save();
                WorkoutSession::where('user_id', $user->id)->delete();
                Workout::where('user_id', $user->id)->delete();
                Exercise::where('user_id', $user->id)->delete();
                Equipment::where('user_id', $user->id)->delete();
                $this->ids['users'][$row['id']] = $user->id;
            }

            // Materiaal (sinds 2026-11-17): standaard op slug, eigen materiaal opnieuw aanmaken
            $standardEquipment = Equipment::whereNull('user_id')->pluck('id', 'slug');
            foreach ($data['equipment'] ?? [] as $row) {
                $this->ids['equipment'][$row['id']] = $row['user_id']
                    ? $this->insert('equipment', $row, ['user_id' => 'users'])
                    : $standardEquipment[$row['slug']] ?? null;
            }

            // Standaardoefeningen op slug, eigen oefeningen opnieuw aanmaken
            $global = Exercise::whereNull('user_id')->pluck('id', 'slug');
            foreach ($data['exercises'] as $row) {
                // Oude dump: materiaal als tekst in exercises.equipment
                if (! array_key_exists('equipment_id', $row)) {
                    $row['equipment_id'] = $standardEquipment[$row['equipment'] ?? 'other'] ?? null;
                    $keys = ['user_id' => 'users'];
                } else {
                    $keys = ['user_id' => 'users', 'equipment_id' => 'equipment'];
                }

                $this->ids['exercises'][$row['id']] = $row['user_id']
                    ? $this->insert('exercises', $row, $keys)
                    : $global[$row['slug']] ?? null;
            }
            foreach ($data['exercise_muscle_group'] as $row) {
                $this->insert('exercise_muscle_group', $row, ['exercise_id' => 'exercises', 'muscle_group_id' => 'muscle_groups'], keepId: true);
            }

            foreach ($data['workouts'] as $row) {
                $this->ids['workouts'][$row['id']] = $this->insert('workouts', $row, ['user_id' => 'users']);
            }
            foreach ($data['workout_exercises'] as $row) {
                $this->ids['workout_exercises'][$row['id']] = $this->insert('workout_exercises', $row, ['workout_id' => 'workouts', 'exercise_id' => 'exercises']);
            }
            foreach ($data['muscle_group_workout'] as $row) {
                $this->insert('muscle_group_workout', $row, ['workout_id' => 'workouts', 'muscle_group_id' => 'muscle_groups'], keepId: true);
            }

            foreach ($data['workout_sessions'] as $row) {
                $this->ids['workout_sessions'][$row['id']] = $this->insert('workout_sessions', $row, ['user_id' => 'users', 'workout_id' => 'workouts']);
            }
            foreach ($data['session_exercises'] as $row) {
                $this->ids['session_exercises'][$row['id']] = $this->insert('session_exercises', $row, ['workout_session_id' => 'workout_sessions', 'exercise_id' => 'exercises', 'workout_exercise_id' => 'workout_exercises']);
            }
            foreach (array_chunk($data['set_logs'], 200) as $chunk) {
                $columns = $this->columns['set_logs'] ??= Schema::getColumnListing('set_logs');
                DB::table('set_logs')->insert(array_map(fn ($row) => Arr::only($this->map($row, ['session_exercise_id' => 'session_exercises']), $columns), $chunk));
            }
            foreach ($data['cardio_logs'] as $row) {
                $this->ids['cardio_logs'][$row['id']] = $this->insert('cardio_logs', $row, ['session_exercise_id' => 'session_exercises']);
            }
            foreach ($data['cardio_splits'] as $row) {
                $this->insert('cardio_splits', $row, ['cardio_log_id' => 'cardio_logs']);
            }
            foreach ($data['scheduled_workouts'] ?? [] as $row) {
                $this->insert('scheduled_workouts', $row, ['user_id' => 'users', 'workout_id' => 'workouts', 'workout_session_id' => 'workout_sessions']);
            }
        });

        $this->command?->info(sprintf('Online gegevens geladen: %d workouts, %d trainingen. Inloggen met wachtwoord "password".',
            count($data['workouts']), count($data['workout_sessions'])));
    }

    /** Rij invoegen met omgezette sleutels; geeft het nieuwe id terug */
    private function insert(string $table, array $row, array $keys, bool $keepId = false): ?int
    {
        $row = Arr::only($this->map($row, $keys, $keepId), $this->columns[$table] ??= Schema::getColumnListing($table));

        if ($keepId) {
            DB::table($table)->insert($row);

            return null;
        }

        return DB::table($table)->insertGetId($row);
    }

    private function map(array $row, array $keys, bool $keepId = false): array
    {
        foreach ($keys as $column => $table) {
            if ($row[$column] !== null) {
                $row[$column] = $this->ids[$table][$row[$column]] ?? throw new \RuntimeException("{$column} {$row[$column]} niet gevonden");
            }
        }

        return $keepId ? $row : Arr::except($row, 'id');
    }

    private function bySlug(array $rows, $local): array
    {
        return collect($rows)->mapWithKeys(fn ($row) => [$row['id'] => $local[$row['slug']] ?? null])->all();
    }
}
