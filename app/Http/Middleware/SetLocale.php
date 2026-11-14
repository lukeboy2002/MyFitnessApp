<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Zet per verzoek de taal (zie App\Support\Locale), ook voor Livewire-verzoeken */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        Locale::apply(Locale::resolve($request));

        return $next($request);
    }
}
