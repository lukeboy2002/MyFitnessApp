<?php

namespace App\Livewire\Sessions;

use App\Actions\AddSessionExercise;
use App\Enums\CardioMetric;
use App\Livewire\Concerns\EditsExerciseTargets;
use App\Livewire\Concerns\PicksExercises;
use App\Models\CardioLog;
use App\Models\Exercise;
use App\Models\SessionExercise;
use App\Models\SetLog;
use App\Models\WorkoutExercise;
use App\Models\WorkoutSession;
use App\Support\ExerciseRecords;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Het trainingsscherm: sets afvinken, cardio-gegevens invullen, timer met pauze en rusttimer.
 * Alles wordt meteen opgeslagen, zodat je kunt verversen of je telefoon kunt vergrendelen.
 */
#[Layout('layouts.app')]
class Run extends Component
{
    use EditsExerciseTargets, PicksExercises;

    public WorkoutSession $session;

    /** Invoer per set: [set_id => ['reps' => .., 'weight' => .., 'seconds' => ..]] */
    public array $values = [];

    /** Invoer per cardio-oefening: [session_exercise_id => ['time' => 'mm:ss', 'distance' => km, 'metric' => .., ...]] */
    public array $cardio = [];

    /** Tussentijden per km: [session_exercise_id => [km => 'mm:ss']] */
    public array $splits = [];

    /** Oefening die je tijdens de training toevoegt: eerst kiezen, dan het doel invullen */
    public ?array $adding = null;

    public bool $confirmingFinish = false;

    public bool $confirmingCancel = false;

    public function mount(WorkoutSession $session)
    {
        $this->authorize('update', $session);
        $this->session = $session;

        if (! $session->isRunning()) {
            return $this->redirectRoute('sessions.summary', $session, navigate: true);
        }

        $this->fillInputs();
    }

    public function updatedValues($value, string $key): void
    {
        [$id, $field] = explode('.', $key);
        $set = $this->findSet((int) $id);
        $value = $this->decimal($value);

        $rules = ['reps' => 'integer|between:0,500', 'weight' => 'numeric|between:-200,999', 'seconds' => 'integer|between:0,36000'];
        if (! isset($rules[$field]) || Validator::make(['v' => $value], ['v' => 'nullable|'.$rules[$field]])->fails()) {
            $this->addError("values.{$id}.{$field}", 'Ongeldige waarde');

            return;
        }

        $this->resetErrorBag("values.{$id}.{$field}");
        $set->update([$field === 'seconds' ? 'duration_seconds' : $field => $value]);
    }

    /** Het vinkje van een set; bij afvinken start de rusttimer */
    public function toggleSet(int $id): void
    {
        $set = $this->findSet($id);
        $done = ! $set->is_completed;

        $set->update(['is_completed' => $done, 'completed_at' => $done ? now() : null]);

        $rest = $set->sessionExercise->rest_seconds;
        if ($done && $rest) {
            $this->dispatch('rest-start', seconds: $rest);
        }

        // Nieuw record: confetti en een melding
        $exercise = $set->sessionExercise->exercise;
        $records = $done ? ExerciseRecords::build($this->session, $exercise->id, $exercise->type) : null;
        if ($records?->isRecordSet($set)) {
            $this->dispatch('record', title: 'Nieuw record!', text: $exercise->name.' · '.$records->describeSet($set));
        }
    }

    public function addSet(int $sessionExerciseId): void
    {
        $item = $this->findExercise($sessionExerciseId);
        $last = $item->sets()->reorder('set_number', 'desc')->first();

        $set = $item->sets()->create([
            ...($last?->only('target_reps', 'target_weight', 'target_duration_seconds') ?? []),
            'set_number' => ($last?->set_number ?? 0) + 1,
            'reps' => $last?->reps ?? $item->target_reps,
            'weight' => $last?->weight ?? $item->target_weight,
            'duration_seconds' => $last?->duration_seconds ?? ($item->exercise->type->usesReps() ? null : $item->target_duration_seconds),
        ]);
        $this->values[$set->id] = $this->setValues($set);
    }

    public function removeSet(int $sessionExerciseId): void
    {
        $item = $this->findExercise($sessionExerciseId);

        if ($item->sets()->count() > 1) {
            $set = $item->sets()->reorder('set_number', 'desc')->first();
            unset($this->values[$set->id]);
            $set->delete();
        }
    }

    public function updatedCardio($value, string $key): void
    {
        [$id, $field] = explode('.', $key);
        $log = $this->findExercise((int) $id)->cardio()->firstOrCreate();
        $value = $this->decimal($value);

        $column = match (true) {
            $field === 'time' => ['duration_seconds', $this->clockSeconds($value)],
            $field === 'distance' => ['distance_m', filled($value) && is_numeric($value) ? (int) round($value * 1000) : null],
            $field === 'metric' => ['metric', CardioMetric::tryFrom((string) $value)],
            $field === 'pace' => ['pace_seconds', $this->clockSeconds($value)],
            in_array($field, ['rotations', 'floors', 'stroke_rate']) => [$field, filled($value) && ctype_digit((string) $value) ? (int) $value : null],
            in_array($field, ['metric_value', 'mets', 'incline']) => [$field, filled($value) && is_numeric($value) ? $value : null],
            array_key_exists($field, CardioLog::FIELDS) => [$field, filled($value) && is_numeric($value) ? (int) $value : null],
            default => null,
        };

        if (! $column || (filled($value) && $column[1] === null && $field !== 'metric')) {
            $this->addError("cardio.{$id}.{$field}", 'Ongeldige waarde');

            return;
        }

        $this->resetErrorBag("cardio.{$id}.{$field}");
        $log->update([$column[0] => $column[1]]);
    }

    /** Tussentijd: hoe lang je over km $number deed */
    public function updatedSplits($value, string $key): void
    {
        // Een lege lijst stuurt de browser in zijn geheel mee: dan elke km apart opslaan
        if (! str_contains($key, '.')) {
            foreach ((array) $value as $number => $time) {
                $this->updatedSplits($time, "{$key}.{$number}");
            }

            return;
        }

        [$id, $number] = array_map('intval', explode('.', $key));
        $log = $this->findExercise($id)->cardio()->firstOrCreate();
        $seconds = $this->clockSeconds($value);

        if ($number < 1) {
            unset($this->splits[$id][$number]);

            return;
        }

        if (filled($value) && ! $seconds) {
            $this->addError("splits.{$id}.{$number}", 'Ongeldige tijd');

            return;
        }

        $this->resetErrorBag("splits.{$id}.{$number}");
        $seconds
            ? $log->splits()->updateOrCreate(['number' => $number], ['duration_seconds' => $seconds])
            : $log->splits()->where('number', $number)->delete();
    }

    /** Oefening toevoegen: kiezer openen */
    public function openAdding(): void
    {
        $this->adding = null;
        $this->resetErrorBag('adding');
        $this->openPicker();
    }

    /** Oefening gekozen: het doel invullen, voorgevuld met het standaarddoel van de oefening */
    public function pick(int $id): void
    {
        $exercise = Exercise::visibleTo(Auth::user())->active()->findOrFail($id);
        $this->adding = $this->toItem($exercise, WorkoutExercise::defaultsFor($exercise));
    }

    public function addExercise(AddSessionExercise $action): void
    {
        abort_unless($this->adding, 404);

        $this->adding = $this->normalizeDecimals($this->adding);
        $this->validate([
            'adding.exercise_id' => ['required', 'integer'],
            ...$this->targetRules('adding'),
        ], attributes: $this->targetAttributes('adding'));

        $exercise = Exercise::visibleTo(Auth::user())->active()->findOrFail($this->adding['exercise_id']);
        $item = $action->handle($this->session, $exercise, $this->targets($exercise->type, $this->adding));

        $this->reset('adding', 'showPicker');
        $this->fillInputs();
        $this->dispatch('exercise-added', id: $item->id);
    }

    /** Knop "Volgende km": een nieuw vakje voor de volgende tussentijd */
    public function addSplit(int $sessionExerciseId): void
    {
        $this->findExercise($sessionExerciseId);

        $splits = $this->splits[$sessionExerciseId] ?? [];
        $this->splits[$sessionExerciseId] = $splits + [($splits ? max(array_keys($splits)) : 0) + 1 => null];
        $this->dispatch('split-added', id: $sessionExerciseId);
    }

    /** Tussentijd weghalen; de km's erna schuiven een plek op */
    public function removeSplit(int $sessionExerciseId, int $number): void
    {
        $log = $this->findExercise($sessionExerciseId)->cardio()->firstOrCreate();

        $log->splits()->where('number', $number)->delete();
        foreach ($log->splits()->where('number', '>', $number)->get() as $split) {
            $split->decrement('number');
        }

        $times = $this->splits[$sessionExerciseId] ?? [];
        unset($times[$number]);
        ksort($times);
        $this->splits[$sessionExerciseId] = $times ? array_combine(range(1, count($times)), array_values($times)) : [];
        $this->resetErrorBag("splits.{$sessionExerciseId}");
    }

    /** Cardio-oefening afronden of weer openzetten */
    public function toggleCardio(int $sessionExerciseId): void
    {
        $item = $this->findExercise($sessionExerciseId);
        $item->update(['completed_at' => $item->completed_at ? null : now()]);

        $new = $item->completed_at
            ? ExerciseRecords::build($this->session, $item->exercise_id, $item->exercise->type)->cardioRecords($item->cardio()->with('splits')->first())
            : [];
        if ($new) {
            $this->dispatch('record', title: count($new) > 1 ? 'Nieuwe records!' : 'Nieuw record!', text: $item->exercise->name.' · '.mb_strtolower(implode(', ', $new)));
        }
    }

    /** wire:sort: oefening $id naar plek $position slepen, bijv. als een machine bezet is */
    public function sortExercise(int $id, int $position): void
    {
        $this->findExercise($id);

        $ids = $this->session->exercises()->pluck('id')->reject(fn ($other) => $other === $id)->values()->all();
        array_splice($ids, max(0, min($position, count($ids))), 0, [$id]);

        foreach ($ids as $index => $exerciseId) {
            $this->session->exercises()->whereKey($exerciseId)->update(['position' => $index + 1]);
        }
    }

    /** Machine bezet: oefening achteraan zetten */
    public function moveToEnd(int $id): void
    {
        $this->sortExercise($id, PHP_INT_MAX);
    }

    public function pause(): void
    {
        $this->session->pause();
    }

    public function resume(): void
    {
        $this->session->resume();
    }

    public function finish()
    {
        $this->session->finish();

        return $this->redirectRoute('sessions.summary', $this->session, navigate: true);
    }

    /** Training stoppen zonder op te slaan */
    public function cancel()
    {
        $workout = $this->session->workout;
        $this->session->delete();

        session()->flash('status', 'De training is gestopt en niet opgeslagen.');

        return $workout
            ? $this->redirectRoute('workouts.show', $workout, navigate: true)
            : $this->redirectRoute($this->session->isQuickstart() ? 'quickstart' : 'workouts.index', navigate: true);
    }

    public function render()
    {
        $this->session->load(['exercises.exercise.muscleGroups', 'exercises.sets', 'exercises.cardio.splits']);
        $sets = $this->session->exercises->flatMap->sets;

        return view('livewire.sessions.run', [
            'setsDone' => $sets->where('is_completed', true)->count(),
            'setsTotal' => $sets->count(),
            'records' => ExerciseRecords::forSession($this->session),
        ])->title($this->session->name);
    }

    private function fillInputs(): void
    {
        $this->session->load(['exercises.sets', 'exercises.cardio.splits']);

        foreach ($this->session->exercises as $item) {
            foreach ($item->sets as $set) {
                $this->values[$set->id] = $this->setValues($set);
            }

            if ($log = $item->cardio) {
                $this->cardio[$item->id] = [
                    'time' => $log->duration_seconds ? sprintf('%d:%02d', intdiv($log->duration_seconds, 60), $log->duration_seconds % 60) : null,
                    'distance' => $log->distance_m ? WorkoutSession::formatNumber($log->distance_m / 1000) : null,
                    'metric' => $log->metric?->value,
                    'metric_value' => filled($log->metric_value) ? WorkoutSession::formatNumber($log->metric_value) : null,
                    'rotations' => $log->rotations,
                    'floors' => $log->floors,
                    'stroke_rate' => $log->stroke_rate,
                    'pace' => CardioLog::pace($log->pace_seconds),
                    ...collect(CardioLog::FIELDS)->keys()->mapWithKeys(fn ($f) => [$f => filled($log->{$f}) ? WorkoutSession::formatNumber($log->{$f}) : null]),
                ];
                $this->splits[$item->id] = $log->splits->mapWithKeys(fn ($split) => [$split->number => CardioLog::pace($split->duration_seconds)])->all();
            }
        }
    }

    private function setValues(SetLog $set): array
    {
        return [
            'reps' => $set->reps,
            'weight' => filled($set->weight) ? WorkoutSession::formatNumber($set->weight) : null,
            'seconds' => $set->duration_seconds,
        ];
    }

    private function findSet(int $id): SetLog
    {
        return SetLog::whereKey($id)
            ->whereHas('sessionExercise', fn ($q) => $q->where('workout_session_id', $this->session->id))
            ->with('sessionExercise.exercise')
            ->firstOrFail();
    }

    private function findExercise(int $id): SessionExercise
    {
        return $this->session->exercises()->with('exercise')->findOrFail($id);
    }

    /** Klok-tijd: "5:32" of "1:02:10" → seconden; een los getal telt als minuten */
    private function clockSeconds($value): ?int
    {
        return $this->seconds(is_string($value) ? str_replace(['.', ','], ':', trim($value)) : $value);
    }

    /** "22,5" → "22.5"; lege invoer → null */
    private function decimal($value)
    {
        if (is_string($value)) {
            $value = str_replace(',', '.', trim($value));
        }

        return $value === '' ? null : $value;
    }

    /** "20", "20:30" of "1:05:00" → seconden */
    private function seconds($value): ?int
    {
        if (blank($value)) {
            return null;
        }

        $parts = array_map('trim', explode(':', (string) $value));
        if (count($parts) > 3 || collect($parts)->contains(fn ($p) => ! ctype_digit($p))) {
            return null;
        }

        return match (count($parts)) {
            1 => (int) $parts[0] * 60,
            2 => (int) $parts[0] * 60 + (int) $parts[1],
            3 => (int) $parts[0] * 3600 + (int) $parts[1] * 60 + (int) $parts[2],
        };
    }
}
