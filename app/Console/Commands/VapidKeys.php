<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** Sleutels voor pushmeldingen maken; zet ze in .env (lokaal en op de server elk een eigen paar) */
class VapidKeys extends Command
{
    protected $signature = 'fitness:vapid-keys';

    protected $description = 'Sleutels voor pushmeldingen maken (VAPID) om in .env te zetten';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->info('Zet deze twee regels in je .env en draai daarna php artisan optimize:clear (op de server: php artisan optimize):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->comment('Bewaar de sleutels goed. Maak je later nieuwe, dan moet iedereen meldingen opnieuw aanzetten.');

        return self::SUCCESS;
    }
}
