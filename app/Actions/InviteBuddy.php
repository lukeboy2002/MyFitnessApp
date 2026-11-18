<?php

namespace App\Actions;

use App\Models\Buddy;
use App\Models\User;
use App\Support\Inbox;
use Illuminate\Support\Str;

/**
 * Maatje uitnodigen op gebruikersnaam of e-mailadres (exact) of op een gekozen zoekresultaat.
 * Alleen tussen openbare profielen. Geeft niets terug over of het account bestaat.
 * Had de ander jou al uitgenodigd, dan zijn jullie meteen maatjes.
 */
class InviteBuddy
{
    public function handle(User $from, string|User $login): ?Buddy
    {
        $to = $login instanceof User ? $login : $this->find($login);

        if (! $to || $to->is($from) || $to->isBanned() || ! $to->profile_public || ! $from->profile_public) {
            return null;
        }

        $existing = Buddy::between($from, $to);

        if ($existing?->isAccepted()) {
            return $existing;
        }

        if ($existing && $existing->user_id === $to->id) {
            return app(AcceptBuddy::class)->handle($existing);
        }

        if ($existing) {
            return $existing; // al uitgenodigd: niet opnieuw een bericht sturen
        }

        $buddy = Buddy::create(['user_id' => $from->id, 'buddy_id' => $to->id]);

        Inbox::deliver($to,
            title: __('Buddy invitation', [], $to->preferredLocale()),
            body: __(':name (@:username) wants to be your buddy, so you can share workouts.', ['name' => $from->name, 'username' => $from->username], $to->preferredLocale()),
            url: route('buddies', absolute: false),
            tag: 'buddy-'.$buddy->id,
            push: $to->push_buddies,
        );

        return $buddy;
    }

    private function find(string $login): ?User
    {
        $login = Str::lower(ltrim(trim($login), '@'));

        return $login === '' ? null : User::query()
            ->when(str_contains($login, '@'),
                fn ($q) => $q->whereRaw('lower(email) = ?', [$login]),
                fn ($q) => $q->whereRaw('lower(username) = ?', [$login]))
            ->first();
    }
}
