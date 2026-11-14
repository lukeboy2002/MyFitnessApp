<?php

namespace App\Livewire\Admin;

use App\Jobs\SendPushBroadcast;
use App\Models\PushBroadcast;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Admin: zelf een pushmelding maken en direct versturen aan iedereen of aan één gebruiker, met geschiedenis */
#[Layout('layouts.app')]
class PushMessages extends Component
{
    public string $audience = 'everyone';

    public ?string $recipient_id = null;

    public string $title = '';

    public string $body = '';

    public string $link = '';

    public function mount(): void
    {
        Gate::authorize('admin');
    }

    /** Gebruikers met pushmeldingen aan, om er één uit te kiezen */
    #[Computed]
    public function recipients()
    {
        return PushBroadcast::audience()->orderBy('name')->pluck('name', 'id');
    }

    /** Alle gebruikers voor de keuzelijst; wie niets kan ontvangen staat erbij met de reden */
    #[Computed]
    public function userOptions()
    {
        return User::query()->withExists('pushSubscriptions')->orderBy('name')->get()
            ->mapWithKeys(fn ($user) => [$user->id => match (true) {
                $this->recipients->has($user->id) => $user->name,
                ! $user->push_subscriptions_exists => $user->name.' ('.__('push off').')',
                ! $user->push_admin => $user->name.' ('.__('messages off').')',
                default => $user->name.' ('.__('Blocked').')',
            }]);
    }

    #[Computed]
    public function reach(): int
    {
        if ($this->audience === 'user') {
            return $this->recipient_id && $this->recipients->has((int) $this->recipient_id) ? 1 : 0;
        }

        return $this->recipients->count();
    }

    #[Computed]
    public function history()
    {
        return PushBroadcast::with('recipient:id,name')->latest('id')->limit(30)->get();
    }

    public function send(): void
    {
        Gate::authorize('admin');

        $this->validate([
            'audience' => ['required', 'in:everyone,user'],
            'recipient_id' => ['nullable', 'required_if:audience,user', Rule::in($this->recipients->keys()->map(fn ($id) => (string) $id)->all())],
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:300'],
            'link' => ['nullable', Rule::in(array_keys(PushBroadcast::LINKS))],
        ], [
            'recipient_id.in' => __('This user does not receive messages from the admin.'),
        ]);

        $toEveryone = $this->audience === 'everyone';
        $recipient = $toEveryone ? null : User::find($this->recipient_id);

        $broadcast = PushBroadcast::create([
            'user_id' => Auth::id(),
            'to_everyone' => $toEveryone,
            'recipient_id' => $recipient?->id,
            'recipient_name' => $recipient?->name,
            'title' => trim($this->title),
            'body' => trim($this->body),
            'url' => $this->link ? route($this->link, absolute: false) : null,
        ]);

        SendPushBroadcast::dispatch($broadcast->id);

        $this->reset('title', 'body', 'link');
        unset($this->history);
        session()->flash('status', __('The push notification is being sent.'));
        $this->redirectRoute('admin.push', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.push-messages', ['configured' => WebPush::configured()])->title(__('Push notifications'));
    }
}
