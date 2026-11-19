<?php

namespace App\Livewire\Buddies;

use App\Actions\AcceptBuddy;
use App\Actions\InviteBuddy;
use App\Actions\LikeTraining;
use App\Models\Buddy;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\FriendActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Maatjes: openbare profielen zoeken en uitnodigen, accepteren of weigeren, ontkoppelen.
 * Alleen met een openbaar profiel (users.profile_public) vind je anderen en ontvang je workouts.
 * Maatjes kunnen elkaar workouts sturen (Workouts › Delen); ze zien elkaars trainingen niet.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    /** Uitnodigingen per uur, tegen het raden van gebruikersnamen */
    public const PER_HOUR = 10;

    /** Zoeksuggesties vanaf zoveel tekens, en hoeveel */
    public const SEARCH_MIN = 2;

    public const SEARCH_LIMIT = 8;

    /** activiteit = gedeelde trainingen, vrienden = koppelen en uitnodigen */
    #[Url(as: 'tab')]
    public string $tab = '';

    public string $login = '';

    public ?string $message = null;

    public function mount(): void
    {
        if (! in_array($this->tab, ['activiteit', 'vrienden'], true)) {
            // Open uitnodigingen of nog geen vrienden: eerst het koppelen
            $this->tab = $this->invitations->isNotEmpty() || FriendActivity::friendIds(Auth::user())->isEmpty() ? 'vrienden' : 'activiteit';
        }
    }

    public function showTab(string $tab): void
    {
        $this->tab = $tab === 'activiteit' ? 'activiteit' : 'vrienden';
        $this->message = null;
    }

    /** Duim geven of terugnemen op een gedeelde training van een vriend */
    public function like(int $sessionId, LikeTraining $like): void
    {
        $like->toggle(Auth::user(), WorkoutSession::findOrFail($sessionId));
        unset($this->activity);
    }

    public function invite(InviteBuddy $invite): void
    {
        $this->validate(['login' => ['required', 'string', 'max:255']], attributes: ['login' => __('username or email')]);

        if (! $this->mayInvite()) {
            return;
        }

        $invite->handle(Auth::user(), $this->login);

        // Altijd dezelfde melding: zo kan niemand uitproberen welke accounts er zijn
        $this->message = __('If this account exists, your invitation has been sent.');
        $this->reset('login');
        $this->forget();
    }

    /** Uitnodigen vanuit de zoeksuggesties (alleen openbare profielen) */
    public function inviteUser(int $id, InviteBuddy $invite): void
    {
        $to = User::publicProfile()->notBanned()->whereKeyNot(Auth::id())->findOrFail($id);

        if (! $this->mayInvite()) {
            return;
        }

        $buddy = $invite->handle(Auth::user(), $to);

        $this->message = $buddy?->isAccepted()
            ? __('You and :name are now buddies.', ['name' => $to->name])
            : __('Invitation sent to :name.', ['name' => $to->name]);
        $this->reset('login');
        $this->forget();
    }

    /** Openbaar profiel aan of uit gezet (component Profiel\PublicProfile op deze pagina) */
    #[On('profile-visibility-changed')]
    public function visibilityChanged(): void
    {
        Auth::user()->refresh();
        $this->reset('login', 'message');
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

    /** Openbare profielen die passen bij wat je typt: naam of gebruikersnaam, of het begin van een e-mailadres */
    #[Computed]
    public function suggestions()
    {
        $user = Auth::user();
        $term = mb_strtolower(ltrim(trim($this->login), '@'));

        if (! $user->profile_public || mb_strlen($term) < self::SEARCH_MIN) {
            return collect();
        }

        $like = str_replace(['%', '\\'], '', $term); // % en \ zijn LIKE-tekens; _ mag (te ruim, nooit te weinig)
        $linked = Buddy::involving($user)->get(['user_id', 'buddy_id'])->flatMap(fn (Buddy $row) => [$row->user_id, $row->buddy_id]);

        return User::publicProfile()->notBanned()
            ->whereKeyNot($user->id)
            ->whereNotIn('id', $linked)
            ->where(fn ($q) => str_contains($term, '@')
                ? $q->whereRaw('lower(email) like ?', [$like.'%'])
                : $q->whereRaw('lower(username) like ?', ['%'.$like.'%'])->orWhereRaw('lower(name) like ?', ['%'.$like.'%']))
            ->orderByRaw('lower(username) = ? desc', [$term])
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get();
    }

    /** Gedeelde trainingen van jou en je vrienden, nieuwste eerst */
    #[Computed]
    public function activity()
    {
        return FriendActivity::feed(Auth::user());
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

    private function mayInvite(): bool
    {
        $user = Auth::user();

        if (! $user->profile_public) {
            $this->addError('login', __('Make your profile public first.'));

            return false;
        }

        if (! RateLimiter::attempt('buddy-invite:'.$user->id, self::PER_HOUR, fn () => true, 3600)) {
            $this->addError('login', __('You sent many invitations. Try again in an hour.'));

            return false;
        }

        return true;
    }

    private function forget(): void
    {
        unset($this->invitations, $this->buddies, $this->suggestions, $this->activity);
    }
}
