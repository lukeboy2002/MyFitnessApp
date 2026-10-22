<?php

namespace App\Livewire\Admin;

use App\Enums\BadgeMetric;
use App\Enums\ExerciseType;
use App\Models\Badge;
use App\Models\Exercise;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Admin: badges en hun drempels (niveaus) beheren; geldt voor alle gebruikers */
#[Layout('layouts.app')]
#[Title('Badgebeheer')]
class Badges extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $icon = 'medal';

    public string $metric = 'sessions';

    public ?int $exercise_id = null;

    /** Drempels als tekst, bv. "1, 10, 25, 50" */
    public string $thresholds = '';

    public string $label = '';

    public string $first_label = '';

    public int $sort_order = 0;

    public bool $is_active = true;

    public bool $confirmingDeletion = false;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public function mount(): void
    {
        Gate::authorize('admin');
    }

    public function create(): void
    {
        Gate::authorize('admin');
        $this->resetForm();
        $this->sort_order = (int) Badge::max('sort_order') + 1;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('admin');
        $badge = Badge::findOrFail($id);

        $this->resetForm();
        $this->editingId = $badge->id;
        $this->fill([
            ...$badge->only('name', 'icon', 'exercise_id', 'label', 'sort_order', 'is_active'),
            'metric' => $badge->metric->value,
            'first_label' => (string) $badge->first_label,
            'thresholds' => implode(', ', $badge->thresholds),
        ]);
        $this->showForm = true;
    }

    public function updatedMetric(): void
    {
        $this->exercise_id = null;
    }

    public function save(): void
    {
        Gate::authorize('admin');
        $metric = BadgeMetric::tryFrom($this->metric);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'icon' => ['required', Rule::in(array_keys(Badge::ICONS))],
            'metric' => ['required', Rule::enum(BadgeMetric::class)],
            'exercise_id' => [Rule::requiredIf($metric?->needsExercise() ?? false), 'nullable', Rule::exists('exercises', 'id')->whereNull('user_id')],
            'thresholds' => ['required', fn ($attribute, $value, $fail) => is_string($parsed = $this->parseThresholds()) ? $fail($parsed) : null],
            'label' => ['required', 'string', 'max:80'],
            'first_label' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
            'is_active' => ['boolean'],
        ], attributes: ['thresholds' => 'drempels', 'name' => 'naam', 'icon' => 'icoon', 'metric' => 'meetwaarde', 'exercise_id' => 'oefening', 'label' => 'naam per niveau', 'first_label' => 'naam eerste niveau', 'sort_order' => 'volgorde']);

        $badge = $this->editingId ? Badge::findOrFail($this->editingId) : new Badge;
        $badge->fill([
            ...Arr::except($data, 'thresholds'),
            'exercise_id' => $metric->needsExercise() ? $data['exercise_id'] : null,
            'first_label' => filled($data['first_label']) ? $data['first_label'] : null,
            'thresholds' => $this->parseThresholds(),
        ])->save();

        $this->showForm = false;
        session()->flash('status', "{$badge->name} is opgeslagen.");
        $this->redirectRoute('admin.badges', navigate: true);
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('admin');
        $badge = Badge::findOrFail($id);

        $this->deletingId = $badge->id;
        $this->deletingName = $badge->name;
        $this->confirmingDeletion = true;
    }

    public function deleteConfirmed(): void
    {
        Gate::authorize('admin');
        $badge = Badge::findOrFail($this->deletingId);
        $badge->delete();

        $this->confirmingDeletion = false;
        $this->reset('deletingId', 'deletingName');
        unset($this->badges);
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeletion = false;
        $this->reset('deletingId', 'deletingName');
    }

    public function toggleActive(int $id): void
    {
        Gate::authorize('admin');
        $badge = Badge::findOrFail($id);
        $badge->update(['is_active' => ! $badge->is_active]);
        unset($this->badges);
    }

    /** Hoe de niveaus gaan heten, live onder het formulier */
    #[Computed]
    public function preview(): array
    {
        $metric = BadgeMetric::tryFrom($this->metric);

        $thresholds = $this->parseThresholds();

        if (! $metric || is_string($thresholds) || $this->label === '') {
            return [];
        }

        $badge = new Badge(['metric' => $metric, 'thresholds' => $thresholds, 'label' => $this->label, 'first_label' => $this->first_label ?: null]);

        return array_map(fn (int $level) => $badge->labelFor($level), array_keys($thresholds));
    }

    #[Computed]
    public function badges()
    {
        return Badge::with('exercise')->orderBy('sort_order')->orderBy('id')->get();
    }

    /** Standaardoefeningen voor oefening-badges (cardio alleen bij afstand) */
    #[Computed]
    public function exercises(): array
    {
        return Exercise::query()->whereNull('user_id')
            ->when($this->metric === BadgeMetric::ExerciseDistance->value, fn ($q) => $q->where('type', ExerciseType::Cardio))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    public function render()
    {
        return view('livewire.admin.badges');
    }

    /**
     * "1, 10, 25" → [1, 10, 25]; decimalen met een punt (21.1). Oplopend, minstens 1, hoogstens 10.
     *
     * @return array<int, int|float>|string de getallen, of wat er mis is
     */
    private function parseThresholds(): array|string
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $this->thresholds)), fn ($part) => $part !== ''));
        $numbers = array_map(fn ($part) => is_numeric($part) ? $part + 0 : null, $parts);

        $error = match (true) {
            $numbers === [] => 'Vul minstens één drempel in.',
            count($numbers) > 10 => 'Hoogstens 10 niveaus.',
            in_array(null, $numbers, true) => 'Alleen getallen, gescheiden door komma\'s. Decimalen met een punt, bv. 21.1.',
            min($numbers) <= 0 => 'Drempels moeten groter zijn dan 0.',
            $numbers !== array_values(array_unique($numbers)) || $numbers !== collect($numbers)->sort()->values()->all() => 'Zet de drempels van laag naar hoog, zonder dubbele.',
            default => null,
        };

        return $error ?? $numbers;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'icon', 'metric', 'exercise_id', 'thresholds', 'label', 'first_label', 'sort_order', 'is_active');
    }
}
