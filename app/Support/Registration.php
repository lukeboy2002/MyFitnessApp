<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Mogen nieuwe mensen een account aanmaken? Een admin zet dit open of dicht op de pagina Instellingen.
 * Zolang dat nog niet is gebeurd, geldt ALLOW_REGISTRATION uit .env.
 */
class Registration
{
    public static function isOpen(): bool
    {
        return (bool) Setting::get('registration_open', config('fortify.allow_registration'));
    }

    public static function open(bool $open = true): void
    {
        Setting::put('registration_open', $open ? '1' : '0');
    }
}
