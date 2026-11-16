<?php

namespace App\Livewire\Admin;

use App\Enums\ContactStatus;
use App\Models\ContactRequest;
use App\Support\Inbox;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin: vragen van gebruikers (Contact) beantwoorden of sluiten. Een antwoord komt bij de gebruiker
 * in Berichten (en als pushmelding). Ververst elke 10 seconden zolang de pagina openstaat (wire:poll).
 */
#[Layout('layouts.app')]
class Contact extends Component
{
    #[Url]
    public ?int $open = null;

    #[Url]
    public string $filter = 'open';

    public string $answer = '';

    public function mount(): void
    {
        Gate::authorize('admin');
    }

    #[Computed]
    public function requests()
    {
        return ContactRequest::with(['user:id,name', 'latestReply'])
            ->when($this->filter === 'open', fn ($q) => $q->open())
            ->latest('updated_at')
            ->limit(100)
            ->get();
    }

    #[Computed]
    public function counts(): array
    {
        return ['open' => ContactRequest::open()->count(), 'all' => ContactRequest::count()];
    }

    #[Computed]
    public function selected(): ?ContactRequest
    {
        return $this->open ? ContactRequest::with(['user', 'replies.author:id,name'])->find($this->open) : null;
    }

    public function choose(int $id): void
    {
        $this->open = $id;
        $this->reset('answer');
        $this->resetErrorBag();
    }

    public function back(): void
    {
        $this->open = null;
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter === 'all' ? 'all' : 'open';
    }

    public function reply(): void
    {
        Gate::authorize('admin');
        $request = $this->selected ?? abort(404);
        $this->validate(['answer' => ['required', 'string', 'max:2000']], attributes: ['answer' => __('answer')]);

        $reply = $request->addReply(Auth::user(), $this->answer, asAdmin: true);
        $this->notifyUser($request, $reply->body);

        $this->reset('answer');
        unset($this->selected, $this->requests, $this->counts);
        $this->dispatch('answered');
    }

    public function close(): void
    {
        Gate::authorize('admin');
        $request = $this->selected ?? abort(404);
        $request->update(['status' => ContactStatus::Closed]);
        unset($this->selected, $this->requests, $this->counts);
    }

    public function reopen(): void
    {
        Gate::authorize('admin');
        $request = $this->selected ?? abort(404);
        $request->update(['status' => ContactStatus::Open]);
        unset($this->selected, $this->requests, $this->counts);
    }

    /** Antwoord in Berichten (in de taal van de gebruiker) en als pushmelding als die aan staat */
    private function notifyUser(ContactRequest $request, string $answer): void
    {
        $user = $request->user;
        if ($user->isBanned()) {
            return;
        }

        Inbox::deliver($user,
            title: __('Answer to your question', [], $user->preferredLocale()),
            body: $request->subject."\n\n".Str::limit($answer, 300),
            url: route('contact.show', $request, absolute: false),
            tag: 'contact-'.$request->id,
            push: $user->push_admin,
        );
    }

    public function render()
    {
        return view('livewire.admin.contact')->title(__('Contact (admin)'));
    }
}
