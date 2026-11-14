<?php

namespace App\Jobs;

use App\Models\PushBroadcast;
use App\Support\WebPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Zet een bericht van de admin in de Berichten van de ontvangers en stuurt het als pushmelding naar wie dat wil */
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

        $recipientId = $broadcast->to_everyone ? null : $broadcast->recipient_id;
        $pushIds = PushBroadcast::audience($recipientId)->pluck('id')->flip();

        $users = 0;
        $delivered = 0;
        PushBroadcast::recipients($recipientId)->each(function ($user) use ($broadcast, $pushIds, &$users, &$delivered) {
            $users++;
            $message = $user->messages()->create([
                'push_broadcast_id' => $broadcast->id,
                'title' => $broadcast->title,
                'body' => $broadcast->body,
                'url' => $broadcast->url,
            ]);

            if ($pushIds->has($user->id)) {
                $delivered += WebPush::send($user, [
                    'title' => $broadcast->title,
                    'body' => $broadcast->body,
                    'url' => route('messages', ['read' => $message->id], absolute: false),
                    'tag' => 'admin-'.$broadcast->id,
                ]);
            }
        });

        $broadcast->update(['users_count' => $users, 'delivered_count' => $delivered, 'sent_at' => now()]);
    }
}
