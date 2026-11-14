<?php

namespace App\Http\Controllers;

use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Taal kiezen. Ingelogd: opgeslagen in je profiel; als gast: in de sessie (inlogscherm) */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locale::isAvailable($locale), 404);

        $request->session()->put(Locale::SESSION_KEY, $locale);
        $request->user()?->update(['locale' => $locale]);

        return back();
    }
}
