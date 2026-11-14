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

/** Admin: een bericht direct versturen aan iedereen of aan één gebruiker (in Berichten + als pushmelding), met geschiedenis */
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

    /** Wie het bericht kan krijgen (id => naam): iedereen behalve geblokkeerden */
    #[Computed]
    public function recipients()
    {
        return PushBroadcast::recipients()->orderBy('name')->pluck('name', 'id');
    }

    /** Ids van wie het ook als pushmelding krijgt */
    #[Computed]
    public function pushIds()
    {
        return PushBroadcast::audience()->pluck('id')->flip();
    }

    /** Keuzelijst; wie geen pushmelding krijgt, ziet het alleen in Berichten */
    #[Computed]
    public function userOptions()
    {
        return $this->recipients->map(fn ($name, $id) => $this->pushIds->has($id) ? $name : $name.' ('.__('only in Messages').')');
    }

    /** [aantal ontvangers, waarvan met pushmelding] */
    #[Computed]
    public function reach(): array
    {
        if ($this->audience === 'user') {
            $id = (int) $this->recipient_id;

            return $this->recipients->has($id) ? [1, (int) $this->pushIds->has($id)] : [0, 0];
        }

        return [$this->recipients->count(), $this->pushIds->count()];
    }

    #[Computed]
    public function history()
    {
        return PushBroadcast::with('recipient:id,name')
            ->withCount(['messages as read_count' => fn ($q) => $q->whereNotNull('read_at')])
            ->latest('id')->limit(30)->get();
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
        session()->flash('status', __('The message is being sent.'));
        $this->redirectRoute('admin.push', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.push-messages', ['configured' => WebPush::configured()])->title(__('Push notifications'));
    }
}
