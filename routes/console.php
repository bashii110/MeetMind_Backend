<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FR-7.4 / PHASES.md Phase 5: reminder scheduling. Hourly keeps the
// "next 24h" window (see TaskRepository::dueForReminder) reasonably
// responsive without hammering the DB — adjust cadence once real usage
// patterns are known. Requires `php artisan schedule:work` (dev) or a
// system cron entry calling `php artisan schedule:run` every minute (prod).
Schedule::command('tasks:send-reminders')->hourly();
