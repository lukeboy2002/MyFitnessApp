<?php

namespace App\Livewire\MuscleGroups;

use App\Livewire\Concerns\ConfirmsDeletion;
use App\Livewire\Concerns\ManagesMasterData;
use App\Models\BodyPart;
use App\Models\MuscleGroup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('MuscleGroups')]
class Index extends Component
{
    use ConfirmsDeletion, ManagesMasterData;

    /** Kleuren uit het logo + aanvullende, goed leesbare tinten */
    public const COLORS = ['#1d63c5', '#00468c', '#0e8ad6', '#0e9f8a', '#65a30d', '#d4a106', '#f97316', '#e5650f', '#db3f6b', '#8b5cf6', '#64748b'];

    #[Url(as: 'deel')]
    public ?int $bodyPartFilter = null;

    public ?int $body_part_id = null;

    public string $name = '';

    public string $color = '#1d63c5';

    public ?string $icon = 'arm';

    public int $sort_order = 0;

    public function create(): void
    {
        $this->authorize('create', MuscleGroup::class);
        $this->reset('name', 'icon');
        $this->body_part_id = $this->bodyPartFilter ?? $this->bodyParts->first()?->id;
        $this->color = self::COLORS[array_rand(self::COLORS)];
        $this->sort_order = (int) MuscleGroup::visibleTo(Auth::user())->max('sort_order') + 1;
        $this->openForm();
    }

    public function edit(int $id): void
    {
        $group = MuscleGroup::visibleTo(Auth::user())->findOrFail($id);
        $this->authorize('update', $group);

        $this->fill($group->only('body_part_id', 'name', 'color', 'icon', 'sort_order'));
        $this->openForm($group->id, $group->imageUrl());
    }

    public function save(): void
    {
        $user = Auth::user();

        $data = $this->validate([
            'body_part_id' => ['required', Rule::exists('body_parts', 'id')->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $user->id))],
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon' => ['nullable', Rule::in(array_keys(self::ICONS))],
            ...$this->imageRules(),
            'sort_order' => ['required', 'integer', 'between:0,999'],
        ], attributes: ['body_part_id' => 'lichaamsdeel', 'name' => 'naam', 'color' => 'kleur', 'icon' => 'icoon', 'image' => 'plaatje', 'sort_order' => 'volgorde']);

        if ($this->editingId) {
            $group = MuscleGroup::findOrFail($this->editingId);
            $this->authorize('update', $group);
        } else {
            $this->authorize('create', MuscleGroup::class);
            $group = new MuscleGroup(['user_id' => $user->id]);
        }

        $group->fill([...Arr::except($data, 'image'), 'slug' => MuscleGroup::uniqueSlug($data['name'], $user->id, $group->id)]);
        $this->applyImage($group, 'musclegroups');
        $group->save();

        $this->done("{$group->name} is opgeslagen.");
        unset($this->muscleGroups);
    }

    protected function deletableModel(): string
    {
        return MuscleGroup::class;
    }

    public function delete(int $id): void
    {
        $group = MuscleGroup::visibleTo(Auth::user())->withCount('muscles', 'exercises')->findOrFail($id);
        $this->authorize('delete', $group);

        if ($group->muscles_count > 0) {
            $this->fail("{$group->name} heeft nog {$group->muscles_count} spier(en). Verplaats of verwijder die eerst.");

            return;
        }

        if ($group->exercises_count > 0) {
            $this->fail("{$group->name} hoort nog bij {$group->exercises_count} oefening(en). Haal hem daar eerst weg.");

            return;
        }

        $this->deleteStoredImage($group);
        $group->delete();
        $this->done("{$group->name} is verwijderd.");
        unset($this->muscleGroups);
    }

    #[Computed]
    public function bodyParts()
    {
        return BodyPart::visibleTo(Auth::user())->orderBy('sort_order')->orderBy('name')->get();
    }

    #[Computed]
    public function muscleGroups()
    {
        return MuscleGroup::visibleTo(Auth::user())
            ->with('bodyPart')
            ->withCount(['muscles' => fn ($q) => $q->visibleTo(Auth::user())])
            ->when($this->bodyPartFilter, fn ($q) => $q->where('body_part_id', $this->bodyPartFilter))
            ->orderBy('sort_order')->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('livewire.muscle-groups.index');
    }
}
