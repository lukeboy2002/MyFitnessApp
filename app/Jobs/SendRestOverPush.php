<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\RestReminder;
use App\Support\WebPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Pushmelding "Rust voorbij", met vertraging in de wachtrij gezet door RestReminder::schedule */
class SendRestOverPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $userId, public string $token, public string $body, public string $url) {}

    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user || ! RestReminder::claim($this->userId, $this->token)) {
            return; // intussen een nieuwe rust, overgeslagen of training afgerond
        }

        WebPush::send($user, ['title' => 'Rust voorbij', 'body' => $this->body, 'url' => $this->url, 'tag' => 'rest']);
    }
}
