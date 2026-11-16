<?php

namespace App\Console\Commands;

use App\Enums\SessionStatus;
use App\Models\User;
use App\Support\TrainingStats;
use App\Support\WebPush;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Traits\Localizable;

/**
 * Weekoverzicht: elke maandagochtend een bericht (en pushmelding) over de week ervoor:
 * aantal trainingen, totale tijd en of het weekdoel gehaald is. Alleen voor wie die week getraind heeft;
 * wie lang niet traint krijgt de niet-actief-melding (B4), geen leeg overzicht.
 */
class SendWeeklySummaries extends Command
{
    use Localizable;

    protected $signature = 'fitness:weekly-summaries';

    protected $description = 'Stuur iedereen die vorige week trainde een weekoverzicht';

    public function handle(): int
    {
        $from = CarbonImmutable::now()->startOfWeek()->subWeek();
        $until = $from->addWeek();

        $users = User::query()
            ->where('push_weekly', true)
            ->where(fn ($q) => $q->whereNull('banned_at')->orWhere('banned_until', '<=', now()))
            ->whereHas('workoutSessions', fn ($q) => $q->where('status', SessionStatus::Completed)
                ->where('started_at', '>=', $from)->where('started_at', '<', $until))
            ->orderBy('id')
            ->get();

        $sent = 0;
        foreach ($users as $user) {
            // Nooit twee keer voor dezelfde week (bv. als de planner een keer dubbel draait)
            if (! Cache::add("weekly-summary:{$user->id}:{$from->toDateString()}", true, now()->addWeek())) {
                continue;
            }

            $this->withLocale($user->preferredLocale(), fn () => $this->summarize($user, $from, $until));
            $sent++;
        }

        $this->info("Weekoverzichten verstuurd: {$sent}");

        return self::SUCCESS;
    }

    private function summarize(User $user, CarbonImmutable $from, CarbonImmutable $until): void
    {
        $totals = (new TrainingStats($user))->totals($from, $until);
        $time = TrainingStats::hours($totals['seconds']);

        $lines = [
            trans_choice(':count training|:count trainings', $totals['count']).' · '.$time,
        ];

        if ($goal = $user->weekly_goal_sessions) {
            $lines[] = $totals['count'] >= $goal
                ? __('Goal of :goal trainings reached!', ['goal' => $goal])
                : __(':count of :goal trainings for your goal.', ['count' => $totals['count'], 'goal' => $goal]);
        }

        if ($minutes = $user->weekly_goal_minutes) {
            $goalTime = TrainingStats::hours($minutes * 60);
            $lines[] = $totals['seconds'] >= $minutes * 60
                ? __('Goal of :time training time reached!', ['time' => $goalTime])
                : __(':time of :goal training time for your goal.', ['time' => $time, 'goal' => $goalTime]);
        }

        $message = $user->messages()->create([
            'title' => __('Your week of :from – :until', [
                'from' => $from->translatedFormat($from->month === $until->subDay()->month ? 'j' : 'j F'),
                'until' => $until->subDay()->translatedFormat('j F'),
            ]),
            'body' => implode("\n", $lines),
            'url' => route('progress.index', absolute: false),
        ]);

        WebPush::send($user, [
            'title' => $message->title,
            'body' => $message->body,
            'url' => route('messages', ['read' => $message->id, 'go' => 1], absolute: false),
            'tag' => 'weekly-'.$from->toDateString(),
        ]);
    }
}
