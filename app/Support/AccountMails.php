<?php

namespace App\Support;

use Illuminate\Notifications\Messages\MailMessage;

/** Tekst (in de taal van de gebruiker) voor de accountmails van Laravel/Fortify (e-mailadres bevestigen, wachtwoord vergeten) */
class AccountMails
{
    public static function verifyEmail(object $notifiable, string $url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Confirm your email address'))
            ->greeting(__('Welcome, :name!', ['name' => $notifiable->name]))
            ->line(__('Great that you created an account. Confirm your email address and you can start training right away.'))
            ->action(__('Confirm email address'), $url)
            ->line(__('The link is valid for :count minutes.', ['count' => config('auth.verification.expire', 60)]))
            ->line(__('Did you not create an account? Then you do not need to do anything.'));
    }

    public static function resetPassword(object $notifiable, string $token): MailMessage
    {
        $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject(__('Set a new password'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__('You asked for a new password. Click the button to choose one.'))
            ->action(__('Choose a new password'), $url)
            ->line(__('The link is valid for :count minutes.', ['count' => $minutes]))
            ->line(__('Did you not request anything? Then you can ignore this mail; your password stays the same.'));
    }
}
