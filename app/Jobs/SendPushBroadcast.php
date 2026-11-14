<?php

namespace App\Jobs;

use App\Models\PushBroadcast;
use App\Support\WebPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Verstuurt een pushmelding van de admin naar iedereen of naar één gebruiker */
class SendPushBroadcast implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public int $broadcastId) {}

    public function handle(): void
    {
        $broadcast = PushBroadcast::find($this->broadcastId);

        if (! $broadcast || $broadcast->sent_at) {
            return;
        }

        $message = [
            'title' => $broadcast->title,
            'body' => $broadcast->body,
            'url' => $broadcast->url ?? '/',
            'tag' => 'admin-'.$broadcast->id,
        ];

        $users = 0;
        $delivered = 0;
        PushBroadcast::audience($broadcast->to_everyone ? null : $broadcast->recipient_id)
            ->each(function ($user) use ($message, &$users, &$delivered) {
                $users++;
                $delivered += WebPush::send($user, $message);
            });

        $broadcast->update(['users_count' => $users, 'delivered_count' => $delivered, 'sent_at' => now()]);
    }
}
