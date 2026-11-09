<?php

use Illuminate\Support\Facades\Schedule;

// Planificateur : `* * * * * php artisan schedule:run` (cron) en production.
Schedule::command('wodi:send-reminders')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=720')->daily();
