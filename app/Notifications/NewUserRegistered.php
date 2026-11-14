<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Mail aan een admin: er heeft zich iemand nieuw geregistreerd */
class NewUserRegistered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New user: :name', ['name' => $this->user->name]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('A new user has registered:'))
            ->line('**'.$this->user->name.'** ('.$this->user->username.')')
            ->line($this->user->email)
            ->line(__('Registered on :date at :time.', ['date' => $this->user->created_at->translatedFormat('j F Y'), 'time' => $this->user->created_at->format('H:i')]))
            ->action(__('View the users'), route('admin.users'))
            ->salutation(__('Regards, :app', ['app' => config('app.name')]));
    }
}
