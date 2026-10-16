<?php

namespace App\Livewire\Muscles;

use App\Livewire\Concerns\ConfirmsDeletion;
use App\Livewire\Concerns\ManagesMasterData;
use App\Models\Muscle;
use App\Models\MuscleGroup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Muscles')]
class Index extends Component
{
    use ConfirmsDeletion, ManagesMasterData;

    #[Url(as: 'groep')]
    public ?int $groupFilter = null;

    #[Url(as: 'zoek', except: '')]
    public string $search = '';

    public ?int $muscle_group_id = null;

    public string $name = '';

    public ?string $latin_name = null;

    public int $sort_order = 0;

    public function create(): void
    {
        $this->authorize('create', Muscle::class);
        $this->reset('name', 'latin_name');
        $this->muscle_group_id = $this->groupFilter ?? $this->muscleGroups->first()?->id;
        $this->sort_order = (int) Muscle::visibleTo(Auth::user())->where('muscle_group_id', $this->muscle_group_id)->max('sort_order') + 1;
        $this->openForm();
    }

    public function edit(int $id): void
    {
        $muscle = Muscle::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('update', $muscle);

        $this->fill($muscle->only('muscle_group_id', 'name', 'latin_name', 'sort_order'));
        $this->openForm($muscle->id);
    }

    public function save(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'muscle_group_id' => ['required', Rule::exists('muscle_groups', 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))],
            'name' => ['required', 'string', 'max:80'],
            'latin_name' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
        ], attributes: ['muscle_group_id' => 'spiergroep', 'name' => 'naam', 'latin_name' => 'Latijnse naam', 'sort_order' => 'volgorde']);

        if ($this->editingId) {
            $muscle = Muscle::findOrFail($this->editingId);
            $this->authorize('update', $muscle);
        } else {
            $this->authorize('create', Muscle::class);
            $muscle = new Muscle(['user_id' => $user->id]);
        }

        $muscle->fill([...$data, 'slug' => Muscle::uniqueSlug($data['name'], $user->id, $muscle->id)])->save();

        $this->done("{$muscle->name} is opgeslagen.");
        unset($this->muscles);
    }

    protected function deletableModel(): string
    {
        return Muscle::class;
    }

    public function delete(int $id): void
    {
        $muscle = Muscle::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('delete', $muscle);

        $muscle->delete();
        $this->done("{$muscle->name} is verwijderd.");
        unset($this->muscles);
    }

    #[Computed]
    public function muscleGroups()
    {
        return MuscleGroup::visibleTo(Auth::user())->with('bodyPart')->orderBy('sort_order')->orderBy('name')->get();
    }

    /** Spieren gegroepeerd per spiergroep, gefilterd op groep en zoekterm */
    #[Computed]
    public function muscles()
    {
        return Muscle::visibleTo(Auth::user())
            ->with('muscleGroup')
            ->when($this->groupFilter, fn ($q) => $q->where('muscle_group_id', $this->groupFilter))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('latin_name', 'like', "%{$this->search}%")))
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->groupBy('muscle_group_id')
            ->sortBy(fn ($muscles) => $muscles->first()->muscleGroup->sort_order);
    }

    public function render()
    {
        return view('livewire.muscles.index');
    }
}
