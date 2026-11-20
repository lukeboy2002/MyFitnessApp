<?php

use App\Models\UserMessage;
use Illuminate\Support\Facades\Schedule;

/*
 * Geplande taken. Op de server één cronregel (zie docs/deployment.md, "Planner"):
 *   * * * * * cd /var/www/myfitnessapp && php artisan schedule:run >> /dev/null 2>&1
 * Lokaal bekijken: php artisan schedule:list
 */

// Trainingsherinnering: om 7:00 een bericht + pushmelding voor de trainingen die vandaag in de kalender staan
Schedule::command('fitness:training-reminders')->dailyAt('07:00');

// Weekoverzicht: maandag om 8:00 een bericht + pushmelding over de week ervoor
Schedule::command('fitness:weekly-summaries')->weeklyOn(1, '08:00');

// Niet-actief-melding: om 10:00 een bericht + pushmelding voor wie al 10 dagen niet heeft getraind
Schedule::command('fitness:inactivity-reminders')->dailyAt('10:00');

// Mislukte jobs (pushmeldingen, mails) ouder dan een week opruimen
Schedule::command('queue:prune-failed', ['--hours' => 168])->daily()->at('03:30');

// Verlopen links van "wachtwoord vergeten" opruimen
Schedule::command('auth:clear-resets')->daily()->at('03:35');

// Gelezen berichten ouder dan een half jaar opruimen (UserMessage::prunable)
Schedule::command('model:prune', ['--model' => [UserMessage::class]])->daily()->at('03:40');

// Importbestanden (Hevy, Strong, FitNotes) die na een dag nog niet zijn geïmporteerd of geannuleerd opruimen
Schedule::command('fitness:prune-imports')->daily()->at('03:45');
