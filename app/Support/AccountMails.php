<?php

namespace App\Support;

use Illuminate\Notifications\Messages\MailMessage;

/** Nederlandse tekst voor de accountmails van Laravel/Fortify (e-mailadres bevestigen, wachtwoord vergeten) */
class AccountMails
{
    public static function verifyEmail(object $notifiable, string $url): MailMessage
    {
        return (new MailMessage)
            ->subject('Bevestig je e-mailadres')
            ->greeting('Welkom, '.$notifiable->name.'!')
            ->line('Leuk dat je een account hebt gemaakt. Bevestig je e-mailadres, dan kun je meteen beginnen met trainen.')
            ->action('E-mailadres bevestigen', $url)
            ->line('De link is '.config('auth.verification.expire', 60).' minuten geldig.')
            ->line('Heb je geen account aangemaakt? Dan hoef je niets te doen.');
    }

    public static function resetPassword(object $notifiable, string $token): MailMessage
    {
        $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject('Nieuw wachtwoord instellen')
            ->greeting('Hallo '.$notifiable->name.',')
            ->line('Je hebt gevraagd om een nieuw wachtwoord. Klik op de knop om er een te kiezen.')
            ->action('Nieuw wachtwoord kiezen', $url)
            ->line('De link is '.$minutes.' minuten geldig.')
            ->line('Heb je niets aangevraagd? Dan kun je deze mail negeren; je wachtwoord blijft hetzelfde.');
    }
}
