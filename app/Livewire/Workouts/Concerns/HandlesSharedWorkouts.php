<?php

namespace App\Livewire\Workouts\Concerns;

use App\Actions\AcceptSharedWorkout;
use App\Models\SharedWorkout;
use Illuminate\Support\Facades\Auth;

/** Een workout van een maatje overnemen (kopie in Actief) of weigeren. Voor Workouts\Index (tab Ontvangen) en Workouts\Show */
trait HandlesSharedWorkouts
{
    public function acceptShare(int $id, AcceptSharedWorkout $accept)
    {
        $copy = $accept->handle($this->openShare($id));
        session()->flash('status', __(':name is now in your workouts.', ['name' => $copy->name]));

        return $this->redirectRoute('workouts.show', $copy, navigate: true);
    }

    public function declineShare(int $id)
    {
        $share = $this->openShare($id);
        $share->update(['declined_at' => now()]);
        session()->flash('status', __('You declined :name.', ['name' => $share->workout->name]));

        return $this->redirectRoute('workouts.index', Auth::user()->openSharedCount() > 0 ? ['ontvangen' => 1] : [], navigate: true);
    }

    private function openShare(int $id): SharedWorkout
    {
        return SharedWorkout::open()->where('recipient_id', Auth::id())->with('workout')->findOrFail($id);
    }
}
