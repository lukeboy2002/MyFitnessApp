<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Een account admin maken, of met --remove weer gewone gebruiker */
class MakeAdmin extends Command
{
    protected $signature = 'fitness:admin
        {email : E-mailadres van het account}
        {--remove : Adminrechten weer afnemen}';

    protected $description = 'Een account admin maken (of met --remove weer gewone gebruiker)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Geen account met e-mailadres '.$this->argument('email').'.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('remove')])->save();
        $this->info($user->is_admin ? "{$user->name} is nu admin." : "{$user->name} is geen admin meer.");

        return self::SUCCESS;
    }
}
