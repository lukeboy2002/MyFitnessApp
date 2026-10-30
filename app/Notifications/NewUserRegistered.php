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
            ->subject('Nieuwe gebruiker: '.$this->user->name)
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('Er heeft zich een nieuwe gebruiker geregistreerd:')
            ->line('**'.$this->user->name.'** ('.$this->user->username.')')
            ->line($this->user->email)
            ->line('Geregistreerd op '.$this->user->created_at->translatedFormat('j F Y \o\m H:i').'.')
            ->action('Bekijk de gebruikers', route('admin.users'))
            ->salutation('Groet, '.config('app.name'));
    }
}
