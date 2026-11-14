<?php

use App\Models\UserMessage;
use Illuminate\Support\Facades\Schedule;

/*
 * Geplande taken. Op de server één cronregel (zie docs/deployment.md, "Planner"):
 *   * * * * * cd /var/www/myfitnessapp && php artisan schedule:run >> /dev/null 2>&1
 * Lokaal bekijken: php artisan schedule:list
 */

// Mislukte jobs (pushmeldingen, mails) ouder dan een week opruimen
Schedule::command('queue:prune-failed', ['--hours' => 168])->daily()->at('03:30');

// Verlopen links van "wachtwoord vergeten" opruimen
Schedule::command('auth:clear-resets')->daily()->at('03:35');

// Gelezen berichten ouder dan een half jaar opruimen (UserMessage::prunable)
Schedule::command('model:prune', ['--model' => [UserMessage::class]])->daily()->at('03:40');
