<?php

namespace App\Console\Commands;

use App\Enums\ExerciseType;
use App\Enums\SessionStatus;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Haalt workouts en trainingen over uit de eerste MyFitness-app.
 *
 * Importeer eerst de .sql-dump in een aparte database (bv. myfitness_old) en draai dan:
 *   php artisan fitness:import-legacy antoine@la-online.nl --database=myfitness_old
 *
 * Nog een keer draaien kan: trainingen die al zijn overgezet (source = myfitness) worden overgeslagen.
 */
class ImportLegacyData extends Command
{
    protected $signature = 'fitness:import-legacy
        {email : E-mailadres van je account in deze app}
        {--database=myfitness_old : Database met de gegevens uit de oude app}
        {--dry-run : Alleen tellen, niets opslaan}';

    protected $description = 'Workouts en trainingen overzetten uit de eerste MyFitness-app';

    public const SOURCE = 'myfitness';

    /** Oude naam => naam in de standaardlijst van deze app */
    private const EXERCISES = [
        'Pec Fly' => 'Pec Fly Machine',
        'Rear Delt' => 'Rear Delt Machine',
        'Pullover' => 'Pullover Machine',
        'Chest Press' => 'Chest Press Machine',
        'Back Extension' => 'Back Extension Machine',
        'AB Crush' => 'AB Crush Machine',
        'Triceps Press' => 'Seated Triceps Press',
        'Bilateral Arm Curl' => 'Bilateral Arm Curl Machine',
        'Biceps Curl' => 'Biceps Curl (Cable Station)',
        'Leg Press' => 'Leg Press Machine',
        'Leg Extension' => 'Leg Extension Machine',
        'Low Row' => 'Low Row V Grip (Cable Station)',
        'High Row' => 'High Row Plated',
        'Lat Pull Down' => 'Lat Pull Down (Cable Station)',
        'Lunge' => 'Lunge (Multi Station)',
        'Torso Twist' => 'Torso Twist (Multi Station)',
        'Squat' => 'Squat (Multi Station)',
        'Push Press' => 'Push Press (Multi Station)',
        'Push Down' => 'Push Down (Multi Station)',
        'Hip Glute Press' => 'Hip Glute Press (Multi Station)',
        'Glute Drive' => 'Glute Drive Plated',
        'Stairmaster' => 'Stair Machine',
        'Indoor rowing' => 'Rowing',
        'Tilt seat calf' => 'Tilt Seat Calf Plated',
        'Hitt Mill' => 'HIIT Mill',
        'Bench Press (Dumbell)' => 'Bench Press (Dumbbell)',
        'Dumbell Row' => 'Dumbbell Row',
    ];

    private Connection $old;

    private User $user;

    /** @var array<int, Exercise|null> oud exercise_id => oefening in deze app */
    private array $exercises = [];

    /** @var array<int, Workout> oud workout_id => workout */
    private array $workouts = [];

    private array $counts = ['workouts' => 0, 'trainingen' => 0, 'oefeningen' => 0, 'sets' => 0, 'cardio' => 0, 'overgeslagen' => 0];

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Geen account gevonden met e-mailadres '.$this->argument('email').'.');

            return self::FAILURE;
        }
        $this->user = $user;

        // Een databasenaam op dezelfde server als deze app, of een SQLite-bestand
        $database = $this->option('database');
        config(['database.connections.legacy' => is_file($database)
            ? ['driver' => 'sqlite', 'database' => $database, 'prefix' => '']
            : [...config('database.connections.'.config('database.default')), 'database' => $database],
        ]);
        $this->old = DB::connection('legacy');

        $oldUserId = $this->old->table('users')->where('email', $user->email)->value('id')
            ?? $this->old->table('workout_sessions')->value('user_id');

        $this->mapExercises();

        DB::beginTransaction();
        try {
            $this->importWorkouts($oldUserId);
            $this->importSessions($oldUserId);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->option('dry-run') ? DB::rollBack() : DB::commit();

        $this->table(['', 'Aantal'], collect($this->counts)->map(fn ($count, $label) => [ucfirst($label), $count])->values()->all());
        $this->info($this->option('dry-run') ? 'Proefrun: er is niets opgeslagen.' : 'Klaar.');

        return self::SUCCESS;
    }

    private function mapExercises(): void
    {
        $missing = [];

        foreach ($this->old->table('exercises')->get() as $old) {
            $name = self::EXERCISES[$old->name] ?? $old->name;
            $exercise = Exercise::query()
                ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $this->user->id))
                ->where(fn ($q) => $q->where('slug', str($name)->slug()->value())->orWhere('name', $name))
                ->orderByRaw('user_id is null desc')
                ->first();

            $this->exercises[$old->id] = $exercise;
            if (! $exercise) {
                $missing[] = $old->name;
            }
        }

        if ($missing) {
            $this->warn('Niet gevonden in deze app (wordt overgeslagen): '.implode(', ', $missing));
        }
    }

    private function importWorkouts(int $oldUserId): void
    {
        $sets = $this->old->table('workout_exercise_sets')->orderBy('set_number')->get()->groupBy('workout_exercise_id');

        foreach ($this->old->table('workouts')->where('user_id', $oldUserId)->orderBy('id')->get() as $i => $old) {
            $existing = Workout::where('user_id', $this->user->id)->where('name', $old->name)->first();
            if ($existing || $old->deleted_at) {
                if ($existing) {
                    $this->workouts[$old->id] = $existing;
                }

                continue;
            }

            $workout = Workout::create([
                'user_id' => $this->user->id,
                'name' => $old->name,
                'slug' => Workout::uniqueSlug($old->name, $this->user->id),
                'description' => $old->description,
                'color' => Workout::COLORS[$i % count(Workout::COLORS)],
                'archived_at' => $old->is_archived ? $old->updated_at : null,
            ]);
            $this->workouts[$old->id] = $workout;
            $this->counts['workouts']++;

            $items = $this->old->table('workout_exercises')->where('workout_id', $old->id)->orderBy('sort_order')->orderBy('id')->get();
            foreach ($items->values() as $position => $item) {
                if (! $exercise = $this->exercises[$item->exercise_id] ?? null) {
                    continue;
                }

                $itemSets = $sets->get($item->id, collect());
                $workout->exercises()->create($exercise->type === ExerciseType::Cardio ? [
                    'exercise_id' => $exercise->id,
                    'position' => $position,
                    'target_duration_seconds' => $item->duration_minutes ? $item->duration_minutes * 60 : null,
                    'target_metric' => $exercise->default_metric,
                    'target_metric_value' => $exercise->default_metric ? $item->intensity : null,
                ] : [
                    'exercise_id' => $exercise->id,
                    'position' => $position,
                    'target_sets' => $itemSets->count() ?: $item->target_sets,
                    'target_reps' => $itemSets->first()->reps ?? $item->reps_per_set,
                    'target_weight' => $itemSets->max('weight_kg') ?? $item->weight_kg,
                    'rest_seconds' => $item->rest_seconds,
                ]);
            }
        }
    }

    private function importSessions(int $oldUserId): void
    {
        $workoutNames = $this->old->table('workouts')->pluck('name', 'id');
        $logs = $this->old->table('session_exercise_logs')->orderBy('sort_order')->orderBy('id')->get()->groupBy('workout_session_id');
        $sets = $this->old->table('session_set_logs')->orderBy('set_number')->get()->groupBy('session_exercise_log_id');
        $watch = $this->old->table('cardio_watch_logs')->get()->keyBy('session_exercise_log_id');

        $sessions = $this->old->table('workout_sessions')->where('user_id', $oldUserId)
            ->whereIn('status', ['completed', 'aborted'])->orderBy('started_at')->get();

        foreach ($sessions as $old) {
            $externalId = self::SOURCE.':'.$old->id;
            if (WorkoutSession::where('user_id', $this->user->id)->where('external_id', $externalId)->exists()) {
                $this->counts['overgeslagen']++;

                continue;
            }

            $workout = $this->workouts[$old->workout_id] ?? null;
            $session = WorkoutSession::create([
                'user_id' => $this->user->id,
                'workout_id' => $workout?->id,
                'name' => $workoutNames[$old->workout_id] ?? WorkoutSession::QUICKSTART_NAME,
                'color' => $workout?->color ?? WorkoutSession::QUICKSTART_COLOR,
                'status' => $old->status === 'completed' ? SessionStatus::Completed : SessionStatus::Cancelled,
                'started_at' => $old->started_at,
                'ended_at' => $old->ended_at,
                'paused_seconds' => (int) $old->paused_duration,
                'duration_seconds' => (int) $old->total_seconds,
                'source' => self::SOURCE,
                'external_id' => $externalId,
                'notes' => $old->notes,
            ]);
            $this->counts['trainingen']++;

            $position = 0;
            foreach ($logs->get($old->id, collect()) as $log) {
                if (! $exercise = $this->exercises[$log->exercise_id] ?? null) {
                    continue;
                }

                $done = $exercise->type === ExerciseType::Cardio
                    ? $this->importCardio($session, $exercise, $log, $watch->get($log->id), $position)
                    : $this->importSets($session, $exercise, $log, $sets->get($log->id, collect()), $position);

                $position += (int) $done;
            }
        }
    }

    private function importSets(WorkoutSession $session, Exercise $exercise, object $log, Collection $sets, int $position): bool
    {
        if ($sets->isEmpty()) {
            return false;
        }

        $item = $session->exercises()->create([
            'exercise_id' => $exercise->id,
            'workout_exercise_id' => $this->workoutExerciseId($session, $exercise),
            'position' => $position,
            'target_sets' => $sets->count(),
            'target_reps' => $sets->first()->reps,
            'target_weight' => $sets->first()->weight_kg,
            'rest_seconds' => $sets->first()->rest_seconds,
            'completed_at' => $sets->every('is_completed') ? $sets->max('completed_at') : null,
            'notes' => $log->device_notes,
        ]);

        foreach ($sets as $set) {
            $item->sets()->create([
                'set_number' => $set->set_number,
                'target_reps' => $set->reps,
                'target_weight' => $set->weight_kg,
                'reps' => $set->reps,
                'weight' => $set->weight_kg,
                'is_completed' => (bool) $set->is_completed,
                'completed_at' => $set->completed_at,
            ]);
            $this->counts['sets']++;
        }
        $this->counts['oefeningen']++;

        return true;
    }

    private function importCardio(WorkoutSession $session, Exercise $exercise, object $log, ?object $watch, int $position): bool
    {
        $measured = collect(['distance_km', 'watts', 'mets', 'act_kcal', 'ttl_kcal', 'avg_heart_rate', 'floors', 'rotations', 'stroke_rate', 'pace', 'avg_act_hr'])
            ->contains(fn ($column) => filled($log->{$column}));
        if (! $log->is_completed && ! $measured && ! $watch) {
            return false; // niet gedaan
        }

        $metric = $exercise->default_metric;
        $item = $session->exercises()->create([
            'exercise_id' => $exercise->id,
            'workout_exercise_id' => $this->workoutExerciseId($session, $exercise),
            'position' => $position,
            'target_duration_seconds' => $log->duration_minutes ? $log->duration_minutes * 60 : null,
            'target_metric' => $metric,
            'target_metric_value' => $metric ? $log->intensity : null,
            'completed_at' => $log->is_completed ? $log->updated_at : null,
            'notes' => $log->device_notes,
        ]);

        // Afstand in km; een enkele keer is er in meters ingevuld (bv. 400 bij de crosstrainer)
        $distance = filled($log->distance_km) ? (float) $log->distance_km : null;
        $distanceM = $distance === null ? null : (int) round($distance >= 100 ? $distance : $distance * 1000);

        $cardio = $item->cardio()->create([
            'duration_seconds' => $log->duration_minutes ? $log->duration_minutes * 60 : null,
            'distance_m' => $distanceM,
            'metric' => $metric,
            'metric_value' => $metric ? $log->intensity : null,
            'avg_watts' => $this->int($log->watts),
            'mets' => $log->mets,
            'active_kcal' => $this->int($log->act_kcal) ?? $this->int($watch?->active_kcal),
            'total_kcal' => $this->int($log->ttl_kcal),
            'avg_heart_rate' => $log->avg_heart_rate ?? $watch?->avg_heart_rate,
            // Hometrainer: de "rotaties" uit de oude app zijn omwentelingen per minuut
            'cadence' => $exercise->slug === 'hometrainer' ? $this->int($log->rotations) : null,
            'rotations' => $exercise->slug === 'hometrainer' ? null : $this->int($log->rotations),
            'incline' => (float) $log->incline_degrees > 0 ? $log->incline_degrees : null,
            'avg_act_hr' => $this->int($log->avg_act_hr),
            'floors' => $log->floors,
            'stroke_rate' => $log->stroke_rate,
            'pace_seconds' => $this->seconds($log->pace),
            'extra_metrics' => $watch ? ['watch' => collect((array) $watch)->except(['id', 'user_id', 'session_exercise_log_id', 'created_at', 'updated_at'])->filter(fn ($v) => $v !== null)->all()] : null,
            'source' => self::SOURCE,
        ]);

        // Tussentijd: de oude app had er één, de tijd van de eerste km
        if ($split = $this->seconds($log->interim)) {
            $cardio->splits()->create(['number' => 1, 'duration_seconds' => $split]);
        }

        $this->counts['oefeningen']++;
        $this->counts['cardio']++;

        return true;
    }

    private function workoutExerciseId(WorkoutSession $session, Exercise $exercise): ?int
    {
        return $session->workout_id
            ? WorkoutExercise::where('workout_id', $session->workout_id)->where('exercise_id', $exercise->id)->value('id')
            : null;
    }

    private function int(mixed $value): ?int
    {
        return filled($value) ? (int) round((float) $value) : null;
    }

    /** "12:30" → 750 */
    private function seconds(?string $clock): ?int
    {
        if (! $clock || ! preg_match('/^(\d+):(\d{1,2})$/', trim($clock), $m)) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }
}
