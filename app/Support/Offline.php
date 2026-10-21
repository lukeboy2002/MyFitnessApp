<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Site offline: alleen admins kunnen er dan in, de rest ziet een melding (middleware BlockWhenOffline).
 * Een admin zet dit aan of uit op de pagina Instellingen. Los van `php artisan down` dat deploy.sh gebruikt.
 */
class Offline
{
    public const DEFAULT_MESSAGE = 'We zijn even offline voor onderhoud. Probeer het straks opnieuw.';

    public static function isOn(): bool
    {
        return (bool) Setting::get('offline', false);
    }

    public static function set(bool $offline): void
    {
        Setting::put('offline', $offline ? '1' : '0');
    }

    public static function message(): string
    {
        return Setting::get('offline_message') ?: self::DEFAULT_MESSAGE;
    }
}
