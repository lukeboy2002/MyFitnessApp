<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Bezig',
            self::Paused => 'Gepauzeerd',
            self::Completed => 'Afgerond',
            self::Cancelled => 'Geannuleerd',
        };
    }

    /** Training loopt nog (ook als hij gepauzeerd is) */
    public function isRunning(): bool
    {
        return in_array($this, [self::Active, self::Paused]);
    }
}
