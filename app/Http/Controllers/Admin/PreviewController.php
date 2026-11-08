<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Notifications\NewUserRegistered;
use App\Support\AccountMails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** Admin: mails en foutpagina's bekijken zoals een gebruiker ze ziet, zonder iets te versturen */
class PreviewController extends Controller
{
    /** Sleutel => titel, voor de lijst op Instellingen */
    public const MAILS = [
        'verify-email' => 'E-mailadres bevestigen',
        'reset-password' => 'Wachtwoord vergeten',
        'new-user' => 'Nieuwe gebruiker (aan admins)',
    ];

    public const ERRORS = [401, 403, 404, 419, 429, 500, 503];

    public function mail(Request $request, string $mail)
    {
        $user = $request->user();

        $message = match ($mail) {
            'verify-email' => AccountMails::verifyEmail($user, URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)])),
            'reset-password' => AccountMails::resetPassword($user, 'voorbeeld-token'),
            'new-user' => (new NewUserRegistered($user))->toMail($user),
            default => abort(404),
        };

        return $message->render();
    }

    public function error(int $code)
    {
        abort_unless(in_array($code, self::ERRORS, true), 404);

        abort($code);
    }
}
