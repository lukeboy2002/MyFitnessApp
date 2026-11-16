<?php

namespace App\Enums;

/** Open = wacht op de admin, Beantwoord = wacht op de gebruiker, Gesloten = klaar (een reactie opent hem weer) */
enum ContactStatus: string
{
    case Open = 'open';
    case Answered = 'answered';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => __('Open (question)'),
            self::Answered => __('Answered'),
            self::Closed => __('Closed'),
        };
    }

    /** Kleur voor x-badge */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'fire',
            self::Answered => 'success',
            self::Closed => 'gray',
        };
    }
}
