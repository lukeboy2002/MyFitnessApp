<?php

namespace App\Console\Commands;

use App\Models\ScheduledWorkout;
use App\Models\User;
use App\Support\WebPush;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Trainingsherinnering: wie vandaag een training in de kalender heeft staan die nog niet gedaan is,
 * krijgt een bericht in Berichten en (als push aan staat) een pushmelding. Elke ochtend via de planner.
 */
class SendTrainingReminders extends Command
{
    protected $signature = 'fitness:training-reminders';

    protected $description = 'Stuur een herinnering voor de trainingen die vandaag in de kalender staan';

    public function handle(): int
    {
        $today = today();

        $planned = ScheduledWorkout::query()
            ->open()
            ->whereDate('scheduled_on', $today)
            ->whereHas('user', fn ($q) => $q->where('push_reminder', true)
                ->where(fn ($q) => $q->whereNull('banned_at')->orWhere('banned_until', '<=', now())))
            ->with('user', 'workout')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id');

        $sent = 0;
        foreach ($planned as $items) {
            $user = $items->first()->user;

            // Nooit twee keer op dezelfde dag (bv. als de planner een keer dubbel draait)
            if (! Cache::add("training-reminder:{$user->id}:{$today->toDateString()}", true, now()->addDay())) {
                continue;
            }

            $this->remind($user, $items);
            $sent++;
        }

        $this->info("Herinneringen verstuurd: {$sent}");

        return self::SUCCESS;
    }

    private function remind(User $user, $items): void
    {
        $locale = $user->preferredLocale();
        $names = $items->pluck('workout.name')->unique()->values();
        $url = $names->count() === 1
            ? route('workouts.show', $items->first()->workout, absolute: false)
            : route('calendar', absolute: false);

        $message = $user->messages()->create([
            'title' => __('On the schedule today', [], $locale),
            'body' => trans_choice(':workouts is planned for today. Good luck!|:workouts are planned for today. Good luck!', $names->count(), [
                'workouts' => $names->join(', ', ' '.__('and', [], $locale).' '),
            ], $locale),
            'url' => $url,
        ]);

        WebPush::send($user, [
            'title' => $message->title,
            'body' => $message->body,
            'url' => route('messages', ['read' => $message->id, 'go' => 1], absolute: false),
            'tag' => 'reminder-'.today()->toDateString(),
        ]);
    }
}
