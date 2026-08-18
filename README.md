# MeetMind AI — Backend (Laravel 12)

REST API for MeetMind AI. See `/docs` at the repo root for the full
Architecture, Design, SRD, and Phases documents this scaffold implements.

## Stack
PHP 8.3+, Laravel 12, MySQL 8, Redis (queues + cache), Laravel Sanctum,
Socialite (Google OAuth), OpenAI API.

## Project layout
```
app/
├── Http/Controllers/Api/V1/  # Thin controllers (versioned)
├── Http/Requests/            # Form Request validation
├── Http/Resources/           # API response shaping
├── Http/Middleware/          # RBAC, rate limiting, etc.
├── Services/                 # Business logic
├── Repositories/             # Contracts + Eloquent implementations
├── Jobs/                     # TranscribeAudioJob, GenerateSummaryJob, ExtractTasksJob
├── Events/ & Listeners/      # Domain events
├── Policies/                 # RBAC per resource
├── Notifications/            # FCM + email
└── Support/ApiResponse.php   # success()/error() envelope trait
```
Each folder has a `.gitkeep` explaining what belongs there until Phase 1+
fills it in — see `PHASES.md`.

## Local setup

> This scaffold was generated without network access to Packagist, so
> `vendor/` isn't installed yet. Run these locally:

```bash
composer install
cp .env.example .env
php artisan key:generate

# Create a MySQL database matching .env (DB_DATABASE=meetmind_ai), then:
php artisan migrate

# Avatar uploads (Phase 1 profile module) are served from storage/app/public:
php artisan storage:link

# Start Redis locally (or via Docker), then run the app + queue worker:
php artisan serve
php artisan queue:work --queue=transcription,ai,notifications,default
```

During development, set `MAIL_MAILER=log` in `.env` so verification/password
-reset emails are written to `storage/logs/laravel.log` instead of requiring
a real mail server.

**Google OAuth (FR-1.2):** the Flutter app signs in with Google natively
(`google_sign_in` package) and posts the resulting access token to
`POST /api/v1/auth/google`, which is verified server-side via Socialite —
no redirect-based web OAuth flow is used, since that doesn't fit a mobile
client. Set `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET` in `.env` (from the
Google Cloud Console) either way, since Socialite still needs them to
verify tokens.

The API will be at `http://localhost:8000`. Confirm it's alive:

```bash
curl http://localhost:8000/api/v1/ping
# {"message":"pong","data":{"app":"MeetMind AI","status":"ok","timestamp":"..."}}
```

Run tests:
```bash
php artisan test
```

## Conventions
- **Controllers** stay thin — no business logic, delegate to a `Services/*Service`.
- **Repositories**: bind `{Resource}RepositoryInterface` → `{Resource}Repository`
  in `AppServiceProvider::register()`. `BaseRepository` gives CRUD for free.
- **Responses**: use the `ApiResponse` trait's `success()`/`error()` helpers
  so every endpoint returns the `{ message, data }` / `{ message, errors }`
  envelope described in `ARCHITECTURE.md` section 5.
- **Queues**: dispatch AI jobs onto named queues (`transcription`, `ai`,
  `notifications`) rather than `default`, so workers can be scaled per queue.

## Next steps (see PHASES.md)
Phases 1–5 are done on the backend:
- **Phase 1** — register, login, Google login, refresh-token rotation,
  logout, forgot/reset password, email verification, profile module
  (`tests/Feature/Auth/AuthTest.php`).
- **Phase 2** — meeting CRUD, tags, participant invites, status
  transitions, basic in-app notifications, minimal per-user workspaces
  (`tests/Feature/Meeting/MeetingTest.php`).
- **Phase 3** — chunked, resumable audio upload session tracking
  (`tests/Feature/AudioFile/AudioUploadTest.php`).
- **Phase 4** — transcription -> summary -> task-extraction pipeline
  (`TranscribeAudioJob` -> `GenerateSummaryJob` -> `ExtractTasksJob`,
  chained, each idempotent and retried with backoff), calling OpenAI
  directly via Laravel's `Http` client rather than a third-party SDK
  package (`tests/Feature/Ai/MeetingAiPipelineTest.php`, which fakes the
  OpenAI HTTP calls — no real API key needed to run the test suite).
- **Phase 5** — task CRUD, comments, attachments, assignment (with
  notification), progress tracking, and the deferred Phase 4 piece:
  confirming a `task_candidate` into a real `Task`. Deadline reminders via
  `tasks:send-reminders`, scheduled hourly (`routes/console.php`)
  (`tests/Feature/Task/TaskTest.php`).

Phase 6 (Notifications & Calendar) is next — FCM push (the in-app
notifications from Phases 2/4/5 already exist; this adds the push
delivery layer) and a calendar aggregation endpoint.

**Running the scheduler:** `php artisan schedule:work` in development, or
a single system cron entry calling `php artisan schedule:run` every
minute in production (standard Laravel convention — the scheduler itself
decides what's actually due to run, per the cadence declared in
`routes/console.php`).

**OpenAI setup:** set `OPENAI_API_KEY` in `.env` to actually run the
pipeline against a real recording (`OPENAI_TRANSCRIBE_MODEL` defaults to
`whisper-1`, `OPENAI_CHAT_MODEL` to `gpt-4o-mini` — override either if you
want different models). Without a key, `AiService`'s calls will fail and
the job will retry 3x (1m/5m/15m backoff) before marking the `AudioFile`
`failed` with the error message attached.

### Endpoints
```
# Auth
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/google
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
GET    /api/v1/auth/email/verify/{id}/{hash}   (signed link from email)
POST   /api/v1/auth/email/resend               (auth:sanctum)
POST   /api/v1/auth/refresh                    (auth:sanctum, refresh token only)
POST   /api/v1/auth/logout                     (auth:sanctum)
POST   /api/v1/auth/logout-all                 (auth:sanctum)
GET    /api/v1/auth/me                         (auth:sanctum)

# Profile
GET    /api/v1/profile                         (auth:sanctum)
POST   /api/v1/profile                         (auth:sanctum, multipart for avatar)

# Workspaces (minimal — full management is Phase 7)
GET    /api/v1/workspaces                      (auth:sanctum)

# Meetings
GET    /api/v1/meetings                        (auth:sanctum, ?status=&category=&tag=&search=&page=)
POST   /api/v1/meetings                        (auth:sanctum)
GET    /api/v1/meetings/{meeting}              (auth:sanctum)
PUT    /api/v1/meetings/{meeting}              (auth:sanctum)
DELETE /api/v1/meetings/{meeting}              (auth:sanctum)
PATCH  /api/v1/meetings/{meeting}/status       (auth:sanctum)
POST   /api/v1/meetings/{meeting}/participants               (auth:sanctum, invite by email)
DELETE /api/v1/meetings/{meeting}/participants/{user}         (auth:sanctum)
POST   /api/v1/meetings/{meeting}/participants/respond        (auth:sanctum, accept/decline own invite)

# Notifications (in-app only, no push yet — push lands in Phase 6)
GET    /api/v1/notifications                   (auth:sanctum)
POST   /api/v1/notifications/read-all          (auth:sanctum)
POST   /api/v1/notifications/{notification}/read (auth:sanctum)

# AI pipeline (Phase 4)
GET    /api/v1/meetings/{meeting}/ai-status          (auth:sanctum, poll while processing)
GET    /api/v1/meetings/{meeting}/transcript          (auth:sanctum, 404 until ready)
GET    /api/v1/meetings/{meeting}/summary             (auth:sanctum, 404 until ready)
GET    /api/v1/meetings/{meeting}/task-candidates     (auth:sanctum)
POST   /api/v1/task-candidates/{taskCandidate}/confirm (auth:sanctum, body overrides the AI's suggestion)
POST   /api/v1/task-candidates/{taskCandidate}/dismiss (auth:sanctum)

# Tasks (Phase 5)
GET    /api/v1/tasks                           (auth:sanctum, ?status=&priority=&meeting_id=&assigned_to_me=&search=&page=)
POST   /api/v1/tasks                           (auth:sanctum)
GET    /api/v1/tasks/{task}                    (auth:sanctum)
PUT    /api/v1/tasks/{task}                    (auth:sanctum)
DELETE /api/v1/tasks/{task}                    (auth:sanctum)
PATCH  /api/v1/tasks/{task}/status             (auth:sanctum)
PATCH  /api/v1/tasks/{task}/progress           (auth:sanctum, 0-100)
PATCH  /api/v1/tasks/{task}/assign             (auth:sanctum, null unassigns)
POST   /api/v1/tasks/{task}/comments           (auth:sanctum)
DELETE /api/v1/tasks/{task}/comments/{comment} (auth:sanctum, author only)
POST   /api/v1/tasks/{task}/attachments        (auth:sanctum, multipart)
DELETE /api/v1/tasks/{task}/attachments/{attachment} (auth:sanctum)

# Audio upload (chunked, resumable)
POST   /api/v1/meetings/{meeting}/recording/init   (auth:sanctum)
POST   /api/v1/audio-files/{audioFile}/chunks       (auth:sanctum, multipart: chunk_index, chunk)
GET    /api/v1/audio-files/{audioFile}/status       (auth:sanctum)
POST   /api/v1/audio-files/{audioFile}/complete     (auth:sanctum)
```

**PHP upload limits:** each chunk request needs `upload_max_filesize` and
`post_max_size` in `php.ini` to comfortably exceed your chosen chunk size
(the frontend's default chunk size is 512KB; `StoreAudioChunkRequest` caps
each chunk at 10MB server-side, so there's headroom to raise the client's
chunk size later without a backend change). Also consider raising
`max_execution_time` if `complete()` is slow on very long recordings,
since it synchronously concatenates every chunk in one request. The same
`upload_max_filesize`/`post_max_size` limits apply to task attachments
(`StoreTaskAttachmentRequest` caps at 20MB).
