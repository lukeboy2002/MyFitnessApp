<?php

namespace App\Livewire\Contact;

use App\Enums\ContactTopic;
use App\Models\User;
use App\Notifications\NewContactMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Contact: een vraag stellen aan de admin, en je eerdere vragen */
#[Layout('layouts.app')]
class Index extends Component
{
    /** Hoeveel nieuwe vragen per uur (tegen per ongeluk dubbel versturen en spam) */
    public const PER_HOUR = 5;

    public string $topic = 'exercise';

    public string $subject = '';

    public string $body = '';

    #[Computed]
    public function requests()
    {
        return Auth::user()->contactRequests()->with('latestReply')->latest('updated_at')->get();
    }

    public function send()
    {
        $user = Auth::user();

        $data = $this->validate([
            'topic' => ['required', Rule::enum(ContactTopic::class)],
            'subject' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
        ], attributes: [
            'subject' => $this->topic === 'exercise' ? __('name of the exercise') : __('subject'),
            'body' => __('message'),
        ]);

        if (! RateLimiter::attempt('contact:'.$user->id, self::PER_HOUR, fn () => true, 3600)) {
            throw ValidationException::withMessages(['body' => __('You have sent many questions. Please try again later.')]);
        }

        $request = $user->contactRequests()->create([
            'topic' => $data['topic'],
            'subject' => trim($data['subject']),
            'user_seen_at' => now(),
        ]);
        $reply = $request->addReply($user, $data['body']);

        Notification::send(User::where('is_admin', true)->get(), new NewContactMessage($reply));

        session()->flash('status', __('Thanks! Your question has been sent to the admin. The answer will appear in Messages.'));

        return $this->redirectRoute('contact.show', $request, navigate: true);
    }

    public function render()
    {
        return view('livewire.contact.index', [
            'topics' => ContactTopic::options(),
        ])->title(__('Contact'));
    }
}
