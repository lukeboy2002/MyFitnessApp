<?php

namespace App\Livewire\Contact;

use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\NewContactMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Eén gesprek met de admin. Ververst elke 10 seconden zolang de pagina openstaat (wire:poll). */
#[Layout('layouts.app')]
class Show extends Component
{
    public ContactRequest $request;

    public string $body = '';

    public function mount(ContactRequest $contactRequest): void
    {
        abort_unless($contactRequest->user_id === Auth::id(), 404);
        $this->request = $contactRequest;
    }

    public function send(): void
    {
        $user = Auth::user();
        $this->validate(['body' => ['required', 'string', 'max:2000']], attributes: ['body' => __('message')]);

        if (! RateLimiter::attempt('contact-reply:'.$user->id, 30, fn () => true, 3600)) {
            throw ValidationException::withMessages(['body' => __('You have sent many messages. Please try again later.')]);
        }

        $reply = $this->request->addReply($user, $this->body);
        Notification::send(User::where('is_admin', true)->get(), new NewContactMessage($reply));

        $this->reset('body');
    }

    public function render()
    {
        // Nieuw antwoord gezien: het bolletje in Mijn vragen en het bericht hierover in Berichten verdwijnen.
        // Alleen schrijven als er iets nieuws is, want de pagina ververst elke 10 seconden.
        if ($this->request->hasUnreadAnswer()) {
            ContactRequest::withoutTimestamps(fn () => $this->request->update(['user_seen_at' => now()]));
            Auth::user()->messages()->whereNull('read_at')
                ->where('url', route('contact.show', $this->request, absolute: false))
                ->update(['read_at' => now()]);
            $this->dispatch('messages-changed', unread: Auth::user()->messages()->whereNull('read_at')->count());
        }

        return view('livewire.contact.show', [
            'replies' => $this->request->replies()->get(),
        ])->title($this->request->subject);
    }
}
