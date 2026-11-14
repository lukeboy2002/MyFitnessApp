<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Geblokkeerde gebruiker die nog ingelogd is (andere sessie, "onthoud mij", passkey): direct uitloggen */
class LogoutBannedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->isBanned()) {
            return $next($request);
        }

        $message = $user->banMessage();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('banned', $message);
    }
}
