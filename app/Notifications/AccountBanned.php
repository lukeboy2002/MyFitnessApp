<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Mail aan de gebruiker: een admin heeft je account geblokkeerd (in de taal van de gebruiker) */
class AccountBanned extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** @param  User  $notifiable */
    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('Your account has been blocked'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($notifiable->banned_until
                ? __('Your account at :app has been blocked until :date. Until then you cannot log in.', ['app' => config('app.name'), 'date' => $notifiable->banned_until->translatedFormat('j F Y, H:i')])
                : __('Your account at :app has been blocked indefinitely. You can no longer log in.', ['app' => config('app.name')]));

        if ($notifiable->ban_reason) {
            $mail->line(__('Reason: :reason', ['reason' => $notifiable->ban_reason]));
        }

        return $mail
            ->line(__('Your trainings and other data are kept.'))
            ->salutation(__('Regards, :app', ['app' => config('app.name')]));
    }
}
