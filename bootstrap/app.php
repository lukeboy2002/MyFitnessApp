<?php

use App\Http\Middleware\BlockWhenOffline;
use App\Http\Middleware\SetLocale;
use App\Support\Locale;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Offline-melding vóór de inlogcheck, zodat gasten de melding zien in plaats van het inlogscherm
        // Taal eerst, zodat ook de offline-pagina in je eigen taal is
        $middleware->web(append: [SetLocale::class, BlockWhenOffline::class]);
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: BlockWhenOffline::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Fout vóór de web-middleware (bv. 404 op een onbekende url): geen sessie, dus taal van de browser
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->hasSession()) {
                Locale::apply(Locale::resolve($request));
            }
        });
    })->create();
