<?php

namespace App\Livewire\BodyParts;

use App\Livewire\Concerns\ManagesMasterData;
use App\Models\BodyPart;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('BodyParts')]
class Index extends Component
{
    use ManagesMasterData;

    public string $name = '';

    public int $sort_order = 0;

    public function create(): void
    {
        $this->authorize('create', BodyPart::class);
        $this->reset('name');
        $this->sort_order = (int) BodyPart::visibleTo(Auth::user())->max('sort_order') + 1;
        $this->openForm();
    }

    public function edit(int $id): void
    {
        $bodyPart = BodyPart::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('update', $bodyPart);

        $this->fill($bodyPart->only('name', 'sort_order'));
        $this->openForm($bodyPart->id);
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
        ], attributes: ['name' => 'naam', 'sort_order' => 'volgorde']);

        $user = Auth::user();

        if ($this->editingId) {
            $bodyPart = BodyPart::findOrFail($this->editingId);
            $this->authorize('update', $bodyPart);
        } else {
            $this->authorize('create', BodyPart::class);
            $bodyPart = new BodyPart(['user_id' => $user->id]);
        }

        $bodyPart->fill([...$data, 'slug' => BodyPart::uniqueSlug($data['name'], $user->id, $bodyPart->id)])->save();

        $this->done("{$bodyPart->name} is opgeslagen.");
        unset($this->bodyParts);
    }

    public function delete(int $id): void
    {
        $bodyPart = BodyPart::visibleTo(Auth::user())->withCount('muscleGroups')->findOrFail($id);
        $this->authorize('delete', $bodyPart);

        if ($bodyPart->muscle_groups_count > 0) {
            $this->fail("{$bodyPart->name} heeft nog {$bodyPart->muscle_groups_count} spiergroep(en). Verplaats of verwijder die eerst.");

            return;
        }

        $bodyPart->delete();
        $this->done("{$bodyPart->name} is verwijderd.");
        unset($this->bodyParts);
    }

    #[Computed]
    public function bodyParts()
    {
        return BodyPart::visibleTo(Auth::user())
            ->with(['muscleGroups' => fn ($q) => $q->visibleTo(Auth::user())])
            ->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.body-parts.index');
    }
}
