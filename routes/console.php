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

// FR-9.1 / PHASES.md Phase 6: meeting start times are far more
// time-sensitive than day-scale task deadlines, so this runs on a much
// tighter cadence — see MeetingRepository::dueForReminder for the
// "starts within the next hour" window this needs to catch reliably.
Schedule::command('meetings:send-reminders')->everyFifteenMinutes();

// Phase 11 (NFR-4/NFR-5 token hygiene): AuthService::issueTokenPair sets
// a real expires_at on both the access and refresh tokens it issues.
// Sanctum's guard already rejects expired tokens on every request, so
// this doesn't change auth behavior — it just stops the
// personal_access_tokens table from growing forever with rows that can
// never authenticate again anyway. Ships as a built-in Sanctum command,
// no custom code needed.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
