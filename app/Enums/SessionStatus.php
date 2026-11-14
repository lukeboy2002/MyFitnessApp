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
            self::Active => __('In progress'),
            self::Paused => __('Paused'),
            self::Completed => __('Completed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /** Training loopt nog (ook als hij gepauzeerd is) */
    public function isRunning(): bool
    {
        return in_array($this, [self::Active, self::Paused]);
    }
}
