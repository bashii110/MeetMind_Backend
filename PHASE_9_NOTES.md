# Phase 9 Backend — Analytics & Admin

Delivers PHASES.md Phase 9's backend: workspace analytics aggregation
(cached in Redis/whatever `CACHE_STORE` is configured), a productivity
score endpoint, and the Admin Dashboard backend (user management,
storage usage, content-moderation queue).

**Nothing here touches `app/Jobs/`, `app/Services/AiService.php`,
`routes/api.php`, `app/Providers/AppServiceProvider.php`, or
`bootstrap/app.php` as full-file replacements** — those are yours.
The last three need one small addition each, delivered below as exact
snippets to paste in by hand. Everything else is a brand-new file.

## New files (drop in as-is)
```
app/Enums/ContentReportStatus.php
app/Models/ReportedContent.php
database/migrations/2024_08_01_000000_create_reported_content_table.php
app/Repositories/Contracts/ReportedContentRepositoryInterface.php
app/Repositories/Eloquent/ReportedContentRepository.php
app/Http/Resources/ReportedContentResource.php
app/Http/Middleware/EnsureSystemAdmin.php
app/Http/Requests/Report/StoreReportRequest.php
app/Http/Requests/Admin/ModerateReportRequest.php
app/Services/ModerationService.php
app/Services/AdminService.php
app/Services/AnalyticsService.php
app/Services/ProductivityScoreService.php
app/Http/Controllers/Api/V1/AnalyticsController.php
app/Http/Controllers/Api/V1/ReportController.php
app/Http/Controllers/Api/V1/AdminController.php
app/Http/Controllers/Api/V1/AdminUserController.php
app/Http/Controllers/Api/V1/AdminReportController.php
tests/Feature/Analytics/AnalyticsTest.php
tests/Feature/Admin/AdminUserTest.php
tests/Feature/Admin/AdminReportTest.php
```

## Integrate

1. Copy the files above into the matching paths in your project root.
2. Apply the three hand-merge snippets below.
3. `php artisan migrate` — adds `reported_content`.
4. `php artisan test`.

## Hand-merge snippet 1 — `bootstrap/app.php`

Register the new middleware alias inside the existing `withMiddleware`
closure, alongside the `api(prepend: ...)` call:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->throttleApi();

        // Phase 9 — gates /api/v1/admin/* to system admins (FR-16.x).
        $middleware->alias([
            'system_admin' => \App\Http\Middleware\EnsureSystemAdmin::class,
        ]);
    })
```

## Hand-merge snippet 2 — `app/Providers/AppServiceProvider.php`

Add the import pair:

```php
use App\Repositories\Contracts\ReportedContentRepositoryInterface;
use App\Repositories\Eloquent\ReportedContentRepository;
```

And bind it inside `register()`, alongside the other bindings:

```php
        $this->app->bind(ReportedContentRepositoryInterface::class, ReportedContentRepository::class);
```

## Hand-merge snippet 3 — `routes/api.php`

Add these imports near the other `App\Http\Controllers\Api\V1\*` ones:

```php
use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AdminReportController;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\ReportController;
```

Add these two routes inside the existing
`Route::middleware('auth:sanctum')->group(...)` block (anywhere is
fine — e.g. right after the `/calendar` route):

```php
        // Phase 9 — Analytics & productivity score (FR-13.1, FR-2.5).
        Route::get('/analytics', [AnalyticsController::class, 'index'])
            ->name('api.v1.analytics.index');

        Route::get('/analytics/productivity-score', [AnalyticsController::class, 'productivityScore'])
            ->name('api.v1.analytics.productivity-score');

        // Phase 9 — report content into the moderation queue (any authenticated user).
        Route::post('/reports', [ReportController::class, 'store'])
            ->name('api.v1.reports.store');
```

Then add this **new, sibling** group inside `Route::prefix('v1')->group(...)`
— i.e. at the same nesting level as the `auth:sanctum` group above, not
inside it (it declares its own `auth:sanctum` + `system_admin` stack):

```php
    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard — Phase 9 (FR-16.x)
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'system_admin'])->prefix('admin')->name('api.v1.admin.')->group(function () {

        Route::get('/overview', [AdminController::class, 'overview'])
            ->name('overview');

        Route::get('/users', [AdminUserController::class, 'index'])
            ->name('users.index');

        Route::patch('/users/{user}/disable', [AdminUserController::class, 'disable'])
            ->name('users.disable');

        Route::patch('/users/{user}/enable', [AdminUserController::class, 'enable'])
            ->name('users.enable');

        Route::get('/reports', [AdminReportController::class, 'index'])
            ->name('reports.index');

        Route::patch('/reports/{report}', [AdminReportController::class, 'update'])
            ->name('reports.update');
    });
```

## Design notes

**Analytics are workspace-scoped, admin views are platform-scoped.**
`GET /analytics` answers "how is *this workspace* doing" (FR-13.1,
DESIGN.md 3.11's chart grid) and is available to any workspace member.
`GET /admin/overview` / `/admin/users` / `/admin/reports` answer "how is
*the platform* doing" (FR-16.x) and are restricted to `role =
system_admin` via the new `system_admin` middleware. These are
deliberately separate concerns with separate authorization models —
folding them into one endpoint would force every analytics call to also
carry admin-check overhead it doesn't need.

**Month bucketing happens in PHP, not raw SQL.** `AnalyticsService::
meetingsPerMonth()` fetches meetings in range and groups them by month
with Carbon, rather than a `DATE_FORMAT`/`strftime` expression — the
same reasoning already applied in `MeetingRepository::dueForReminder`
and `CalendarService`, since the test suite runs against SQLite while
production targets MySQL and the two engines don't share a date-grouping
function.

**Short-TTL caching, not write-time invalidation.** `AnalyticsService::
forWorkspace()` and `AdminService::overview()` both cache their result
for a few minutes via the default cache store (`Cache::remember`)
rather than being busted on every meeting/task/user mutation across the
codebase. A dashboard chart being up to ~10 minutes stale is an
acceptable trade-off against wiring cache invalidation into a dozen
unrelated write paths; tightening this later (e.g. Redis tags, or
event-driven invalidation) is straightforward if real usage calls for
fresher numbers.

**Storage usage is summed from recorded sizes, not a disk walk.**
`AdioFile.total_size`, `TaskAttachment.size`, and `WorkspaceFile.size`
are already populated at upload time by earlier phases, so
`AdminService::storageUsage()` is three cheap `SUM()` queries — no
filesystem traversal, and it stays correct even if storage moves to S3
later (ARCHITECTURE.md §7 flags this as a future possibility).

**Reporting reuses the existing view policies.** `ModerationService::
report()` requires the same authorization a user would need to see the
content in the first place (`MeetingPolicy::view` / `TaskPolicy::view`)
before letting them file a report against it — a stranger can't
discover-and-report content they have no visibility into. This mirrors
`MeetingAiController`/`TaskCandidateController`'s existing pattern of
calling `Gate`/`$this->authorize()` before acting on a resource.

**Reportable subjects are whitelisted, not client-supplied class
names.** Same convention as `TaskCandidate`'s AI-suggested assignee
matching and `ActivityLog`'s subject morph: `ModerationService::
SUBJECT_MAP` maps a small set of known strings (`task`, `meeting`,
`task_comment`) to their model classes, so a report request can never
be crafted to attach itself to an arbitrary Eloquent model.

**Productivity score formula is explicit and periodized, not all-time.**
`ProductivityScoreService` defaults to the current calendar week and
documents its point weights directly in the class docblock (on-time
completions, late completions, open overdue tasks, attended meetings)
rather than as an unexplained "score" black box — see the docblock in
`app/Services/ProductivityScoreService.php` for the exact formula. It's
intentionally simple; refining the weights or adding more signals (e.g.
comment activity, file shares) is a product decision for later, not a
backend limitation.

**Disabling a user revokes every token immediately.** `AdminService::
disable()` sets `is_disabled` and also calls `$user->tokens()->delete()`
— the same "force sign-out everywhere" pattern already used by
`AuthService::resetPassword()` — so a disabled account can't keep using
an already-issued access token until it naturally expires.

## Deferred

- **Frontend** (Analytics Dashboard charts, Admin Dashboard screens) is
  intentionally not part of this delivery, per the project's standing
  backend-first convention.
- **Acting on a report** (e.g. auto-deleting or redacting the reported
  subject when an admin marks it `actioned`) is left as a manual,
  out-of-band step for the admin today — `ModerationService::resolve()`
  only updates the report's own status/resolution metadata. Wiring
  `actioned` to an automatic takedown of the underlying Task/Meeting/
  TaskComment is a reasonable follow-up once there's a policy for what
  "actioned" should actually do to each content type.
- **Cache invalidation tuning** for analytics/overview beyond the fixed
  short TTL (see design notes above) is deferred until real usage shows
  it's needed.
- **Department/user activity breakdowns beyond the top-5 leaderboard**
  (FR-13.1 also mentions "department... activity") aren't broken out
  separately yet, since `Department` (Phase 7) is explicitly optional
  and thinly used — the existing workspace-wide `active_users`
  leaderboard covers the "most active users" chart from DESIGN.md 3.11
  as specified.

## Endpoints added
```
GET   /api/v1/analytics?workspace_id=                 (auth:sanctum)
GET   /api/v1/analytics/productivity-score             (auth:sanctum)
POST  /api/v1/reports                                   (auth:sanctum — { subject_type, subject_id, reason, details? })

GET   /api/v1/admin/overview                            (auth:sanctum, system_admin)
GET   /api/v1/admin/users?search=&is_disabled=&page=    (auth:sanctum, system_admin)
PATCH /api/v1/admin/users/{user}/disable                (auth:sanctum, system_admin)
PATCH /api/v1/admin/users/{user}/enable                 (auth:sanctum, system_admin)
GET   /api/v1/admin/reports?status=&page=               (auth:sanctum, system_admin)
PATCH /api/v1/admin/reports/{report}                    (auth:sanctum, system_admin — { status, notes? })
```
