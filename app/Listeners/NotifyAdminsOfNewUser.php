<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\NewUserRegistered;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Notification;

/** Na een registratie: alle admins krijgen een mail (via de queue, zodat registreren niet hoeft te wachten) */
class NotifyAdminsOfNewUser
{
    public function handle(Registered $event): void
    {
        $admins = User::where('is_admin', true)->whereKeyNot($event->user->getKey())->get();

        Notification::send($admins, new NewUserRegistered($event->user));
    }
}
