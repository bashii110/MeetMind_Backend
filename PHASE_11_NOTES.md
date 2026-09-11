# Phase 11 Backend — Security Hardening & QA

PHASES.md's Phase 11 covers rate limiting, an input-validation/RBAC
audit, a SQLi/XSS/CSRF/token-hygiene checklist, and expanded test
coverage. This delivery is a real audit of the actual codebase, not a
generic checklist — it found and fixed two genuine IDOR bugs and a
validation gap, closed a rate-limiting hole, added token-hygiene
housekeeping, and documents what was checked and found clean.

**`routes/api.php` and `app/Providers/AppServiceProvider.php` are not
touched as full-file replacements** — three small route-attribute
changes and one new rate limiter, delivered as exact snippets below.
`app/Services/TaskService.php` and `app/Http/Controllers/Api/V1/
TaskController.php` are full replacements (not on your protected list).
`routes/console.php` is also a full replacement — it only had two lines
in it before, low risk to just replace.

## Modified files (full replacements — copy over the existing ones)
```
app/Services/TaskService.php                    — + cross-workspace validation guards
app/Http/Controllers/Api/V1/TaskController.php  — + IDOR fixes on comment/attachment deletion
routes/console.php                              — + scheduled Sanctum token pruning
```

## New files
```
tests/Feature/Security/TaskIdorTest.php
tests/Feature/Security/TaskAssignmentValidationTest.php
tests/Feature/Security/RateLimitingTest.php
```

## Integrate

1. Copy the three modified files above into place.
2. Apply the two hand-merge snippets below.
3. No migration needed this phase.
4. `php artisan test` — the two `RateLimitingTest` cases will fail until
   step 2's `routes/api.php` snippet is applied.

## Hand-merge snippet 1 — `app/Providers/AppServiceProvider.php`

Add a new rate limiter inside the existing `boot()` method, alongside
`api`/`auth` (and `ai`, if you already added it in Phase 8):

```php
        RateLimiter::for('verification', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->id ?: $request->ip());
        });
```

(`RateLimiter`, `Limit`, and `Request` are already imported in that file
for the existing limiters.)

## Hand-merge snippet 2 — `routes/api.php`

Three existing route definitions get a `->middleware(...)` call added.
Nothing else about them changes.

**1. Email verification resend** (currently unthrottled — a user could
otherwise be spammed with verification emails, or used to burn mail-
sending quota):

```php
// Before:
Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
    ->name('resend-verification');

// After:
Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
    ->middleware('throttle:verification')
    ->name('resend-verification');
```

**2. Audio recording completion** (triggers the full transcribe →
summarize → extract-tasks AI pipeline — the same class of endpoint
ARCHITECTURE.md §6 calls out for AI-endpoint throttling, alongside the
Phase 8 assistant query):

```php
// Before:
Route::post(
    '/audio-files/{audioFile}/complete',
    [AudioFileController::class, 'complete']
)->name('api.v1.audio.complete');

// After:
Route::post(
    '/audio-files/{audioFile}/complete',
    [AudioFileController::class, 'complete']
)->middleware('throttle:ai')->name('api.v1.audio.complete');
```

**3. Content reporting** (Phase 9 — otherwise spammable to flood the
moderation queue):

```php
// Before:
Route::post('/reports', [ReportController::class, 'store'])
    ->name('api.v1.reports.store');

// After:
Route::post('/reports', [ReportController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('api.v1.reports.store');
```

(This one uses Laravel's inline `throttle:max,minutes` syntax directly —
no new named limiter needed for a single route.)

## Security review — findings and fixes

### 1. IDOR: task attachment deletion (fixed)
`TaskController::destroyAttachment(Task $task, TaskAttachment
$attachment)` authorized only against `$task` (the one in the URL) and
never checked that `$attachment` actually belonged to it. Anyone with
"update" rights on *any* task they own could delete an attachment
belonging to a **completely different task — including one in a
workspace they have no access to** — just by supplying its numeric ID.
**Fixed** with `abort_if($attachment->task_id !== $task->id, 404)`,
matching the tenant-isolation pattern already used elsewhere
(`DepartmentController`, `WorkspaceFileController`). Regression test:
`TaskIdorTest::test_an_attachment_cannot_be_removed_through_an_unrelated_task_url`.

### 2. IDOR: task comment deletion (fixed, lower severity)
`TaskController::destroyComment()` had the identical missing check, but
the existing `$comment->user_id !== $request->user()->id` guard meant
the practical impact was limited to "delete your own comment via the
wrong task's URL" — not privilege escalation, since you can't delete
someone else's comment either way. Fixed anyway for correct REST
semantics and defense in depth. Regression test:
`TaskIdorTest::test_a_comment_cannot_be_deleted_through_an_unrelated_task_url`.

### 3. Validation gap: cross-workspace task assignment (fixed)
`AssignTaskRequest`/`StoreTaskRequest` validated `assigned_user_id` with
only `exists:users,id` — confirming the user exists *anywhere on the
platform*, not that they're a member of the task's workspace. A task
could be assigned to a total stranger. **Fixed** in
`TaskService::assertAssigneeIsWorkspaceMember()`, called from `create()`
and `assign()` (and therefore `confirmCandidate()`, which routes through
`create()`). Enforced at the service layer rather than in each
FormRequest so every entry point is covered by one check.

### 4. Information disclosure: cross-workspace meeting linking (fixed)
Same shape of bug: `meeting_id` was validated with only
`exists:meetings,id`. A workspace member could create/update a task
pointing at a meeting from a **different workspace they don't belong
to**, and `TaskResource::meeting_title` would then display that
meeting's title to them — a minor but real cross-tenant information
leak. **Fixed** in `TaskService::assertMeetingBelongsToWorkspace()`,
called from both `create()` and `update()`.

### 5. Rate limiting (audited, two gaps closed)
`auth`/`login`/`register`/password-reset were already throttled (Phase
0/1), and the AI assistant query was already throttled (Phase 8). Two
gaps found and closed via the snippet above: email-verification resend
(unthrottled), and audio-recording completion (triggers the same AI
pipeline cost as the assistant, but wasn't covered). Content reporting
(Phase 9) also picked up a throttle while auditing — not AI-related, but
an obvious spam vector for the moderation queue.

### 6. SQL injection — audited, no issues found
Every query in the codebase goes through Eloquent or the parameterized
query builder. The only `selectRaw`/`orderByRaw` usages found
(`AnalyticsService`'s aggregate counts, `TaskRepository`'s priority
sort) are static strings with no user input interpolated into them.
No changes needed.

### 7. XSS — not applicable server-side
This is a JSON API; the only Blade view (`welcome.blade.php`) is static
marketing copy with no user-controlled data. There's no admin web
dashboard rendering user content as HTML. XSS risk here is entirely a
Flutter-side concern (e.g. if AI-generated summary text is ever rendered
through a WebView or markdown-to-HTML renderer) — out of scope for this
backend delivery, but worth flagging to whoever builds that screen.

### 8. CSRF — documented finding, no code change
`bootstrap/app.php` prepends `EnsureFrontendRequestsAreStateful` to the
`api` middleware group, which is a Sanctum SPA-auth mechanism intended
to be paired with CSRF protection for cookie-based clients. **Nothing in
this app currently establishes a session cookie** — `AuthController::
login()` always returns bearer tokens, never calls `Auth::login()` — so
this middleware is currently inert and not exploitable today. It becomes
a real CSRF risk only if/when a browser-based, cookie-authenticated
client (e.g. a future admin web dashboard) is added against one of the
`SANCTUM_STATEFUL_DOMAINS`. Flagging this now rather than fixing
something that isn't broken yet: **before adding any cookie-based
frontend**, pair it with Sanctum's CSRF cookie route and Laravel's
standard CSRF middleware, or remove `EnsureFrontendRequestsAreStateful`
entirely if bearer-token auth remains the only client forever.

### 9. Token expiry/rotation — already solid, added hygiene
`AuthService::issueTokenPair()` already issues short-TTL access tokens
and long-TTL, single-purpose refresh tokens (ability-gated so a refresh
token can't be used as a normal bearer token), with rotation on
`/auth/refresh` and full revocation on logout/logout-all/password-reset.
No gaps found in the mechanism itself. Added: a daily
`sanctum:prune-expired --hours=24` schedule (`routes/console.php`) so
`personal_access_tokens` doesn't grow forever with rows that can never
authenticate again anyway — pure housekeeping, no behavior change.

## Deferred

- **Frontend test suite** (Flutter widget/unit tests, cross-device QA
  across Android/iOS and light/dark mode) is out of scope for this
  backend-only delivery, per the earlier decision.
- **Policy objects for every ad-hoc ownership check** — e.g.
  `NotificationController::markRead()`'s inline `$notification->user_id
  !== $request->user()->id` check works correctly today and was audited
  as safe, but converting it (and similar inline checks) to formal
  Policy classes for consistency is a style improvement, not a security
  fix, and is left for a later cleanup pass.
- **Removing `EnsureFrontendRequestsAreStateful`** — left in place since
  it's currently inert (see finding #8); revisit if/when a cookie-based
  client is actually added.
