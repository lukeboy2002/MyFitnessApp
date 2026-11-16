<?php

namespace App\Console\Commands;

use App\Enums\SessionStatus;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Support\WebPush;
use Illuminate\Console\Command;
use Illuminate\Support\Traits\Localizable;

/**
 * Niet-actief-melding: wie al DAYS dagen niet heeft getraind, krijgt een vriendelijk bericht (en pushmelding).
 * Eén keer per periode zonder training: pas na een nieuwe training kan er weer een komen.
 * Wie nog nooit heeft getraind of nu een training heeft lopen, krijgt niets.
 */
class SendInactivityReminders extends Command
{
    use Localizable;

    public const DAYS = 10;

    protected $signature = 'fitness:inactivity-reminders';

    protected $description = 'Stuur een "we missen je" aan wie al '.self::DAYS.' dagen niet heeft getraind';

    public function handle(): int
    {
        $lastTraining = WorkoutSession::query()
            ->selectRaw('max(started_at)')
            ->whereColumn('workout_sessions.user_id', 'users.id')
            ->where('status', SessionStatus::Completed);

        $users = User::query()
            ->select('users.*')
            ->selectSub($lastTraining, 'last_trained_at')
            ->withCasts(['last_trained_at' => 'datetime'])
            ->where('push_inactive', true)
            ->where(fn ($q) => $q->whereNull('banned_at')->orWhere('banned_until', '<=', now()))
            ->whereDoesntHave('workoutSessions', fn ($q) => $q->running())
            ->orderBy('id')
            ->get()
            ->filter(fn (User $user) => $user->last_trained_at
                && $user->last_trained_at < now()->subDays(self::DAYS)
                && (! $user->inactive_reminded_at || $user->inactive_reminded_at < $user->last_trained_at));

        foreach ($users as $user) {
            $this->withLocale($user->preferredLocale(), fn () => $this->remind($user));
        }

        $this->info("Niet-actief-meldingen verstuurd: {$users->count()}");

        return self::SUCCESS;
    }

    private function remind(User $user): void
    {
        $days = (int) $user->last_trained_at->startOfDay()->diffInDays(today());

        $message = $user->messages()->create([
            'title' => __('We miss you!'),
            'body' => __('Your last training was :days days ago. How about a training today? Even a short one counts.', ['days' => $days]),
            'url' => route('workouts.index', absolute: false),
        ]);

        $user->forceFill(['inactive_reminded_at' => now()])->save();

        WebPush::send($user, [
            'title' => $message->title,
            'body' => $message->body,
            'url' => route('messages', ['read' => $message->id, 'go' => 1], absolute: false),
            'tag' => 'inactive',
        ]);
    }
}
