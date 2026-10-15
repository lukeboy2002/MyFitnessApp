<?php

namespace App\Livewire\Exercises;

use App\Enums\ExerciseType;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

class Form extends Component
{
    use WithFileUploads;

    public ?Exercise $exercise = null;

    public string $name = '';

    public string $type = 'strength';

    public string $description = '';

    public ?string $equipment = null;

    public array $muscle_group_ids = [];

    public $image = null;

    // Standaardwaarden kracht
    public ?int $default_sets = 3;

    public ?int $default_reps = 10;

    public ?float $default_weight = null;

    // Standaardwaarden cardio
    public ?int $default_minutes = 20;

    public ?int $default_intensity = null;

    public function mount(?Exercise $exercise = null): void
    {
        if ($exercise?->exists) {
            $this->authorize('update', $exercise);
            $this->exercise = $exercise;
            $this->fill($exercise->only('name', 'description', 'equipment', 'default_sets', 'default_reps', 'default_weight', 'default_intensity'));
            $this->type = $exercise->type->value;
            $this->default_minutes = $exercise->default_duration_seconds ? intdiv($exercise->default_duration_seconds, 60) : null;
            $this->muscle_group_ids = $exercise->muscleGroups->pluck('id')->all();
        }
    }

    public function removeImage(): void
    {
        $this->image = null;
        // bestaande afbeelding pas bij opslaan verwijderen, of hier direct — naar wens
    }

    public function save()
    {
        $data = $this->validate();

        $exercise = $this->exercise ?? new Exercise(['user_id' => auth()->id()]);
        $exercise->fill([
            ...collect($data)->except('image', 'muscle_group_ids', 'default_minutes')->all(),
            'slug' => str($data['name'])->slug(),
            'default_duration_seconds' => $data['default_minutes'] ? $data['default_minutes'] * 60 : null,
        ]);

        if ($this->image) {
            $exercise->image_path = $this->image->store('exercises', 'public');
        }

        $exercise->save();
        $exercise->muscleGroups()->sync($data['muscle_group_ids']);

        session()->flash('status', 'Oefening opgeslagen.');

        return $this->redirectRoute('exercises.show', $exercise, navigate: true);
    }

    #[Layout('components.layouts.app')]
    #[Title('Oefening')]
    public function render()
    {
        return view('livewire.exercises.form', [
            'muscleGroups' => MuscleGroup::orderBy('sort_order')->get(),
        ]);
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(ExerciseType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'equipment' => ['nullable', 'string', 'max:100'],
            'image' => ['nullable', 'image', 'max:4096'],
            'muscle_group_ids' => ['array'],
            'muscle_group_ids.*' => ['exists:muscle_groups,id'],
            'default_sets' => ['nullable', 'integer', 'between:1,20'],
            'default_reps' => ['nullable', 'integer', 'between:1,100'],
            'default_weight' => ['nullable', 'numeric', 'between:0,999'],
            'default_minutes' => ['nullable', 'integer', 'between:1,600'],
            'default_intensity' => ['nullable', 'integer', 'between:1,30'],
        ];
    }
}
