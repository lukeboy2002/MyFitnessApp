<?php

namespace App\Livewire\Buddies;

use App\Actions\AcceptBuddy;
use App\Actions\InviteBuddy;
use App\Models\Buddy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Maatjes: uitnodigen op gebruikersnaam of e-mailadres, accepteren of weigeren, ontkoppelen.
 * Maatjes kunnen elkaar workouts sturen (Workouts › Delen); ze zien elkaars trainingen niet.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    /** Uitnodigingen per uur, tegen het raden van gebruikersnamen */
    public const PER_HOUR = 10;

    public string $login = '';

    public ?string $message = null;

    public function invite(InviteBuddy $invite): void
    {
        $this->validate(['login' => ['required', 'string', 'max:255']], attributes: ['login' => __('username or email')]);

        $user = Auth::user();
        if (! RateLimiter::attempt('buddy-invite:'.$user->id, self::PER_HOUR, fn () => true, 3600)) {
            $this->addError('login', __('You sent many invitations. Try again in an hour.'));

            return;
        }

        $invite->handle($user, $this->login);

        // Altijd dezelfde melding: zo kan niemand uitproberen welke accounts er zijn
        $this->message = __('If this account exists, your invitation has been sent.');
        $this->reset('login');
        $this->forget();
    }

    public function accept(int $id, AcceptBuddy $accept): void
    {
        $buddy = Buddy::pending()->where('buddy_id', Auth::id())->findOrFail($id);
        $accept->handle($buddy);

        $this->message = __('You and :name are now buddies.', ['name' => $buddy->user->name]);
        $this->forget();
    }

    /** Uitnodiging weigeren (aan jou) of intrekken (van jou) */
    public function decline(int $id): void
    {
        Buddy::pending()->involving(Auth::user())->findOrFail($id)->delete();
        $this->message = null;
        $this->forget();
    }

    /** Ontkoppelen: gedeelde workouts die je al overnam blijven gewoon van jou */
    public function remove(int $id): void
    {
        $buddy = Buddy::accepted()->involving(Auth::user())->with('user', 'buddy')->findOrFail($id);
        $name = $buddy->other(Auth::user())->name;
        $buddy->delete();

        $this->message = __(':name is no longer your buddy.', ['name' => $name]);
        $this->forget();
    }

    /** Uitnodigingen aan jou */
    #[Computed]
    public function invitations()
    {
        return Buddy::pending()->where('buddy_id', Auth::id())->with('user')->latest()->get();
    }

    /** Maatjes en jouw uitnodigingen die nog openstaan */
    #[Computed]
    public function buddies()
    {
        $user = Auth::user();

        return Buddy::involving($user)
            ->where(fn ($q) => $q->whereNotNull('accepted_at')->orWhere('user_id', $user->id))
            ->with('user', 'buddy')
            ->get()
            ->sortBy([fn ($a, $b) => $a->isAccepted() <=> $b->isAccepted(), fn ($a, $b) => strcasecmp($a->other($user)->name, $b->other($user)->name)])
            ->values();
    }

    public function render()
    {
        return view('livewire.buddies.index')->title(__('Buddies'));
    }

    private function forget(): void
    {
        unset($this->invitations, $this->buddies);
    }
}
