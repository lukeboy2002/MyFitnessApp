<?php

namespace App\Livewire\Workouts;

use App\Models\RoutineItem;
use App\Models\Workout;
use App\Support\Routine as RoutineSchedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Schema instellen: eigen workouts in een vaste volgorde (slepen), toevoegen, weghalen,
 * en kiezen wie aan de beurt is. Het dashboard toont de volgende (App\Support\Routine).
 */
#[Layout('layouts.app')]
class Routine extends Component
{
    /** Maximaal aantal plekken in een schema */
    public const MAX = 14;

    public ?int $workoutId = null;

    public function add(): void
    {
        $this->validate([
            'workoutId' => ['required', 'integer', Rule::exists('workouts', 'id')->where('user_id', Auth::id())->whereNull('archived_at')],
        ], attributes: ['workoutId' => __('workout')]);

        if ($this->items->count() >= self::MAX) {
            $this->addError('workoutId', __('A schedule has at most :max workouts.', ['max' => self::MAX]));

            return;
        }

        RoutineItem::create(['user_id' => Auth::id(), 'workout_id' => $this->workoutId, 'position' => $this->items->count()]);
        $this->reset('workoutId');
        $this->forget();
    }

    public function remove(int $id): void
    {
        $this->item($id)->delete();
        $this->renumber($this->items->pluck('id')->reject(fn ($row) => $row === $id)->values()->all());
    }

    /** wire:sort: plek $id naar positie $position slepen */
    public function sort(int $id, int $position): void
    {
        $order = $this->items->pluck('id')->reject(fn ($row) => $row === $id)->values()->all();
        array_splice($order, max(0, min($position, count($order))), 0, [$this->item($id)->id]);
        $this->renumber($order);
    }

    /** Het hele schema leegmaken (de workouts zelf blijven bestaan) */
    public function clear(): void
    {
        Auth::user()->forceFill(['next_routine_item_id' => null])->save();
        RoutineItem::where('user_id', Auth::id())->delete();
        $this->forget();
    }

    /** Deze plek is de volgende */
    public function makeNext(int $id): void
    {
        RoutineSchedule::choose(Auth::user(), $this->item($id)->id);
        $this->forget();
    }

    #[Computed]
    public function items()
    {
        return RoutineSchedule::items(Auth::user());
    }

    #[Computed]
    public function current(): ?RoutineItem
    {
        return RoutineSchedule::current(Auth::user());
    }

    /** Eigen actieve workouts om toe te voegen: [id => naam] */
    #[Computed]
    public function workoutOptions(): array
    {
        return Workout::where('user_id', Auth::id())->whereNull('archived_at')->orderBy('name')->pluck('name', 'id')->all();
    }

    public function render()
    {
        return view('livewire.workouts.routine')->title(__('Schedule'));
    }

    private function item(int $id): RoutineItem
    {
        return RoutineItem::where('user_id', Auth::id())->findOrFail($id);
    }

    /** @param  array<int, int>  $order  ids in de nieuwe volgorde */
    private function renumber(array $order): void
    {
        foreach ($order as $position => $id) {
            RoutineItem::where('user_id', Auth::id())->whereKey($id)->update(['position' => $position]);
        }
        $this->forget();
    }

    private function forget(): void
    {
        unset($this->items, $this->current);
    }
}
