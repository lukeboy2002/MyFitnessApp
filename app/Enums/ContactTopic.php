<?php

namespace App\Enums;

/** Waar een vraag aan de admin over gaat (Contact) */
enum ContactTopic: string
{
    case Exercise = 'exercise';
    case Bug = 'bug';
    case Question = 'question';
    case Idea = 'idea';

    public function label(): string
    {
        return match ($this) {
            self::Exercise => __('Request an exercise'),
            self::Bug => __('Report a problem'),
            self::Question => __('Question'),
            self::Idea => __('Idea'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Exercise => __('An exercise you are missing'),
            self::Bug => __('Something does not work well'),
            self::Question => __('How does something work?'),
            self::Idea => __('A wish for the app'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Exercise => 'dumbbell',
            self::Bug => 'alert',
            self::Question => 'info',
            self::Idea => 'bolt',
        };
    }

    /** Kleur van het icoontje in Admin > Contact */
    public function iconClasses(): string
    {
        return match ($this) {
            self::Exercise => 'bg-orange-50 text-fire-600 dark:bg-fire-500/15 dark:text-fire-400',
            self::Bug => 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400',
            self::Question => 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300',
            self::Idea => 'bg-amber-50 text-amber-600 dark:bg-amber-400/15 dark:text-amber-300',
        };
    }

    /** Voor x-form.radio-cards */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $topic) => [
            $topic->value => ['label' => $topic->label(), 'icon' => $topic->icon(), 'description' => $topic->description()],
        ])->all();
    }
}
