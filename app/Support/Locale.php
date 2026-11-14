<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Taal van de app. Volgorde: je eigen keuze (profiel), dan de keuze op het inlogscherm (sessie),
 * dan de taal van je browser, en anders APP_LOCALE.
 */
class Locale
{
    public const SESSION_KEY = 'locale';

    /** @return array<string, string> code => naam in die taal, bv. ['nl' => 'Nederlands'] */
    public static function available(): array
    {
        return config('app.locales', []);
    }

    public static function isAvailable(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::available());
    }

    public static function resolve(Request $request): string
    {
        $choices = [
            $request->user()?->locale,
            $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null,
            $request->getPreferredLanguage(array_keys(self::available())),
        ];

        foreach ($choices as $locale) {
            if (self::isAvailable($locale)) {
                return $locale;
            }
        }

        return config('app.locale');
    }

    /** Zet de taal voor teksten, datums (Carbon) en getallen */
    public static function apply(string $locale): void
    {
        App::setLocale($locale);
    }

    /** Getal met de decimaal-tekens van de huidige taal: 82,5 (nl/de) of 82.5 (en) */
    public static function decimalSeparator(): string
    {
        return App::getLocale() === 'en' ? '.' : ',';
    }

    /** Label midden in een zin: kleine letters, behalve in het Duits (zelfstandige naamwoorden houden hun hoofdletter) */
    public static function lower(string $text): string
    {
        return App::getLocale() === 'de' ? $text : mb_strtolower($text);
    }
}
