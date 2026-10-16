<?php

namespace App\Livewire\BodyParts;

use App\Livewire\Concerns\ManagesMasterData;
use App\Models\BodyPart;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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

    public ?string $icon = 'body';

    public int $sort_order = 0;

    public function create(): void
    {
        $this->authorize('create', BodyPart::class);
        $this->reset('name', 'icon');
        $this->sort_order = (int) BodyPart::visibleTo(Auth::user())->max('sort_order') + 1;
        $this->openForm();
    }

    public function edit(int $id): void
    {
        $bodyPart = BodyPart::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('update', $bodyPart);

        $this->fill($bodyPart->only('name', 'icon', 'sort_order'));
        $this->openForm($bodyPart->id, $bodyPart->imageUrl());
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', Rule::in(array_keys(self::ICONS))],
            ...$this->imageRules(),
            'sort_order' => ['required', 'integer', 'between:0,999'],
        ], attributes: ['name' => 'naam', 'icon' => 'icoon', 'image' => 'plaatje', 'sort_order' => 'volgorde']);

        $user = Auth::user();

        if ($this->editingId) {
            $bodyPart = BodyPart::findOrFail($this->editingId);
            $this->authorize('update', $bodyPart);
        } else {
            $this->authorize('create', BodyPart::class);
            $bodyPart = new BodyPart(['user_id' => $user->id]);
        }

        $bodyPart->fill([...Arr::except($data, 'image'), 'slug' => BodyPart::uniqueSlug($data['name'], $user->id, $bodyPart->id)]);
        $this->applyImage($bodyPart, 'bodyparts');
        $bodyPart->save();

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

        $this->deleteStoredImage($bodyPart);
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
