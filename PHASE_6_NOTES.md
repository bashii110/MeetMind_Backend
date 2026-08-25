# Phase 6 Backend — Notifications & Calendar

This delivers PHASES.md Phase 6's backend half: FCM push, device-token
management, and the calendar aggregation endpoint. It's built as a set of
new files plus a small number of modified existing files (each modified
file below is the *full* file — copy it over the one from Phase 5,
don't hand-merge).

## Integrate into your Phase 5 tree

1. Copy every file under this zip's `app/`, `database/`, `routes/`, and
   `tests/` into the matching paths in your project root, overwriting
   the four modified files listed below.
2. `php artisan migrate` — adds `device_tokens` and
   `meetings.last_reminder_sent_at`.
3. Set in `.env`:
   ```
   FIREBASE_PROJECT_ID=your-firebase-project-id
   FIREBASE_CREDENTIALS=/absolute/path/to/service-account.json
   ```
   (`config/services.php` already had these keys since Phase 0 — nothing
   to add there.) Download the service account JSON from Firebase
   Console → Project Settings → Service Accounts → "Generate new private
   key", and place it at the path above (default:
   `storage/app/firebase/service-account.json`).
4. `php artisan test` to confirm everything's green in your environment
   (all new files pass `php -l` here; full suite needs your MySQL/Redis
   setup or the sqlite/array testing config already in `phpunit.xml`).
5. Run the scheduler as usual (`php artisan schedule:work` in dev) — the
   new `meetings:send-reminders` command is registered alongside
   Phase 5's `tasks:send-reminders`.

## New files
```
database/migrations/2024_06_01_000000_create_device_tokens_table.php
database/migrations/2024_06_01_000001_add_last_reminder_sent_at_to_meetings_table.php
app/Models/DeviceToken.php
app/Repositories/Contracts/DeviceTokenRepositoryInterface.php
app/Repositories/Eloquent/DeviceTokenRepository.php
app/Services/FcmService.php
app/Services/CalendarService.php
app/Support/NotificationCopy.php
app/Jobs/SendPushNotificationJob.php
app/Console/Commands/SendMeetingReminders.php
app/Http/Requests/DeviceToken/RegisterDeviceTokenRequest.php
app/Http/Requests/DeviceToken/UnregisterDeviceTokenRequest.php
app/Http/Requests/Calendar/CalendarRequest.php
app/Http/Controllers/Api/V1/DeviceTokenController.php
app/Http/Controllers/Api/V1/CalendarController.php
tests/Feature/DeviceToken/DeviceTokenTest.php
tests/Feature/Calendar/CalendarTest.php
tests/Feature/Notification/PushNotificationTest.php
```

## Modified files (full replacements)
```
app/Models/User.php                                  — added deviceTokens() relation
app/Models/Meeting.php                                — added last_reminder_sent_at fillable/cast
app/Services/NotificationService.php                  — notify() now also dispatches SendPushNotificationJob
app/Repositories/Contracts/MeetingRepositoryInterface.php  — + forUserBetweenDates, dueForReminder
app/Repositories/Eloquent/MeetingRepository.php             — implements the above
app/Repositories/Contracts/TaskRepositoryInterface.php      — + forUserWithDeadlineBetween
app/Repositories/Eloquent/TaskRepository.php                 — implements the above
app/Providers/AppServiceProvider.php                  — binds DeviceTokenRepositoryInterface
routes/api.php                                        — + /device-tokens, /calendar
routes/console.php                                    — + meetings:send-reminders schedule
```

## Design notes

**No third-party SDK, per the project's standing convention.** `FcmService`
hand-implements the RFC 7523 JWT Bearer grant for the Firebase service
account — builds and RS256-signs the assertion with PHP's built-in
`openssl_sign`, exchanges it at Google's token endpoint, then calls the
FCM HTTP v1 REST API directly through Laravel's `Http` client. No
`kreait/firebase-php`, same pattern as `AiService` calling OpenAI
directly.

**Single choke point for push.** Every existing notification producer
(meeting invites, task assignment/completion, deadline reminders) already
funnels through `NotificationService::notify()`. Phase 6 wires push in
*there* — one line, `SendPushNotificationJob::dispatch(...)` — rather
than touching each of the four listeners/commands individually. New
notification types get push for free.

**Dead-token pruning.** When FCM reports `NOT_FOUND` / `UNREGISTERED` /
`INVALID_ARGUMENT` for a token (uninstalled app, cleared data, rotated
token we never heard about), `FcmService` deletes that `device_tokens`
row immediately instead of retrying it forever.

**Meeting reminders.** `NotificationType::MeetingReminder` existed in the
enum since Phase 2 but had no producer. Added `meetings:send-reminders`
(scheduled every 15 minutes — meeting start times are far more
time-sensitive than day-scale task deadlines) mirroring Phase 5's
`tasks:send-reminders`. Deliberately does the date+time comparison in
PHP with Carbon rather than a raw SQL expression, since `date`/`time` are
separate columns and combining them portably across MySQL (prod) and
SQLite (test suite) isn't practical in one query.

**Calendar endpoint is view-agnostic.** `GET /api/v1/calendar?start=...&end=...`
returns `{ meetings: [...], task_deadlines: [...] }` for whatever range
the frontend's currently-visible month/week/day grid needs — the backend
has no concept of "views," matching DESIGN.md 3.8's "color-coded dots for
meetings vs. task deadlines."

**Device tokens are keyed by token, not by user.** An FCM registration
token belongs to one app install. If the same token shows up under a
different user (shared device, previous account logged out), it's
reassigned via `updateOrCreate` rather than creating a duplicate row —
otherwise a shared/reused device would accumulate stale rows and
eventually get pushes for accounts no longer signed in on it.

## Deferred
Google Calendar sync (PHASES.md marks it optional) is not included here.

## Endpoints added
```
POST   /api/v1/device-tokens                  (auth:sanctum) — { token, platform?, device_name? }
DELETE /api/v1/device-tokens                  (auth:sanctum) — { token }
GET    /api/v1/calendar?start=YYYY-MM-DD&end=YYYY-MM-DD  (auth:sanctum)
```
