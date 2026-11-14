<?php

namespace App\Livewire\Admin;

use App\Enums\SessionStatus;
use App\Models\User;
use App\Notifications\AccountBanned;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin: overzicht van de accounts, details openklappen (dan is een nieuw account gezien), adminrechten geven of afnemen, blokkeren en de blokkade opheffen */
#[Layout('layouts.app')]
class Users extends Component
{
    /** Opengeklapte gebruiker */
    public ?int $open = null;

    /** Blokkeren: welk account, hoelang (1/7/30 dagen, date of forever), tot welke datum en de reden */
    public bool $showBan = false;

    public ?int $banning = null;

    public string $ban_duration = '7';

    public ?string $ban_until = null;

    public string $ban_reason = '';

    public const BAN_DURATIONS = ['1' => '1 day', '7' => '1 week', '30' => '1 month', 'date' => 'Until a date', 'forever' => 'Indefinitely'];

    public function mount(): void
    {
        Gate::authorize('admin');
    }

    public function toggle(int $id): void
    {
        Gate::authorize('admin');

        $this->open = $this->open === $id ? null : $id;

        if ($this->open !== null && $id !== Auth::id()) {
            User::whereKey($id)->whereNull('seen_by_admin_at')->update(['seen_by_admin_at' => now()]);
            unset($this->users);
        }
    }

    public function toggleAdmin(int $id): void
    {
        Gate::authorize('admin');
        abort_if($id === Auth::id(), 403, __('You cannot take away your own admin rights.'));

        $user = User::findOrFail($id);
        $user->forceFill(['is_admin' => ! $user->is_admin])->save();
        unset($this->users);
    }

    public function startBan(int $id): void
    {
        Gate::authorize('admin');
        $this->guardBan(User::findOrFail($id));

        $this->resetValidation();
        $this->banning = $id;
        $this->ban_duration = '7';
        $this->ban_until = null;
        $this->ban_reason = '';
        $this->showBan = true;
    }

    public function ban(): void
    {
        Gate::authorize('admin');
        $user = User::findOrFail($this->banning);
        $this->guardBan($user);

        $this->validate([
            'ban_duration' => ['required', 'in:'.implode(',', array_keys(self::BAN_DURATIONS))],
            'ban_until' => ['nullable', 'required_if:ban_duration,date', 'date', 'after:today'],
            'ban_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $until = match ($this->ban_duration) {
            'forever' => null,
            'date' => Carbon::parse($this->ban_until)->endOfDay(),
            default => now()->addDays((int) $this->ban_duration),
        };

        $user->forceFill([
            'banned_at' => now(),
            'banned_until' => $until,
            'ban_reason' => trim($this->ban_reason) ?: null,
            'remember_token' => Str::random(60),
        ])->save();

        // Direct uit op alle apparaten; de middleware vangt een nog lopend verzoek op
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $user->notify(new AccountBanned);

        $this->showBan = false;
        $this->banning = null;
        unset($this->users);
    }

    public function unban(int $id): void
    {
        Gate::authorize('admin');

        User::findOrFail($id)->forceFill(['banned_at' => null, 'banned_until' => null, 'ban_reason' => null])->save();
        unset($this->users);
    }

    private function guardBan(User $user): void
    {
        abort_if($user->is(Auth::user()) || $user->is_admin, 403, __('You cannot block yourself or another admin.'));
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->withCount(['workouts', 'workoutSessions as trainings_count' => fn ($q) => $q->where('status', SessionStatus::Completed)])
            ->withMax(['workoutSessions as last_training_at' => fn ($q) => $q->where('status', SessionStatus::Completed)], 'started_at')
            ->orderByDesc('created_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.admin.users')->title(__('Users'));
    }
}
