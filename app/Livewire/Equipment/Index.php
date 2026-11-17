<?php

namespace App\Livewire\Equipment;

use App\Livewire\Concerns\ConfirmsDeletion;
use App\Livewire\Concerns\ManagesMasterData;
use App\Models\Equipment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Materiaal: de standaardlijst (voor iedereen, alleen door een admin te wijzigen) en je eigen materiaal,
 * zoals "Smith machine" of "Hack squat". Kies je daarna bij een oefening en filter je op bij Oefeningen.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use ConfirmsDeletion, ManagesMasterData;

    public string $name = '';

    public int $sort_order = 0;

    public function create(): void
    {
        $this->authorize('create', Equipment::class);
        $this->reset('name');
        $this->sort_order = (int) Equipment::where('user_id', Auth::id())->max('sort_order') + 1;
        $this->openForm();
    }

    public function edit(int $id): void
    {
        $equipment = Equipment::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('update', $equipment);

        $this->name = $equipment->label();
        $this->sort_order = $equipment->sort_order;
        $this->openForm($equipment->id);
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
        ], attributes: ['name' => __('name'), 'sort_order' => __('order')]);

        if ($this->editingId) {
            $equipment = Equipment::findOrFail($this->editingId);
            $this->authorize('update', $equipment);
        } else {
            $this->authorize('create', Equipment::class);
            $equipment = new Equipment(['user_id' => Auth::id()]);
        }

        // Ongewijzigde naam van standaardmateriaal: de Engelse sleutel houden, dan blijft het vertaald
        if ($equipment->exists && $equipment->isStandard() && $data['name'] === $equipment->label()) {
            $data['name'] = $equipment->name;
        }

        $equipment->fill([...$data, 'slug' => $equipment->slugFor($data['name'])])->save();

        $this->done(__(':name is saved.', ['name' => $equipment->label()]));
        unset($this->equipment);
    }

    protected function deletableModel(): string
    {
        return Equipment::class;
    }

    public function delete(int $id): void
    {
        $equipment = Equipment::visibleTo(Auth::user())->withCount('exercises')->findOrFail($id);
        $this->authorize('delete', $equipment);

        if ($equipment->exercises_count > 0) {
            $this->fail(trans_choice(':name is still used by :count exercise. Choose other equipment there first.|:name is still used by :count exercises. Choose other equipment there first.', $equipment->exercises_count, ['name' => $equipment->label()]));

            return;
        }

        $equipment->delete();
        $this->done(__(':name is deleted.', ['name' => $equipment->label()]));
        unset($this->equipment);
    }

    /** Standaard eerst, dan je eigen materiaal; met het aantal oefeningen dat jij ziet */
    #[Computed]
    public function equipment()
    {
        return Equipment::visibleTo(Auth::user())
            ->withCount(['exercises' => fn ($q) => $q->visibleTo(Auth::user())->active()])
            ->orderByRaw('user_id is not null')->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.equipment.index')->title(__('Equipment'));
    }
}
