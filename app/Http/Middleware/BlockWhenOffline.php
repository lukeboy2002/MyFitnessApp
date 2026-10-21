<?php

namespace App\Http\Middleware;

use App\Support\Offline;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Staat de site offline (Instellingen), dan zien alleen admins de app; inloggen blijft mogelijk */
class BlockWhenOffline
{
    /** Routes die ook offline werken, zodat een admin kan inloggen */
    protected array $except = [
        'login', 'login.store', 'logout', 'two-factor.login', 'two-factor.login.store', 'passkey.login', 'passkey.login-options',
        'password.request', 'password.email', 'password.reset', 'password.update',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // rescue: nog geen settings-tabel (vóór php artisan migrate) = gewoon online
        if (! rescue(fn () => Offline::isOn(), false, report: false) || $request->user()?->isAdmin() || $request->routeIs(...$this->except)) {
            return $next($request);
        }

        return response()->view('offline', ['message' => Offline::message()], 503)->header('Retry-After', '300');
    }
}
