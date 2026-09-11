# Phase 10 Backend — Offline Support & Sync Hardening

PHASES.md's Phase 10 is framed almost entirely as frontend work (Hive/
Drift caching, an offline recording queue, an outbox pattern, conflict
resolution *UI*). This delivery is the backend half that a Flutter
outbox/cache actually needs to talk to, derived from FR-15.1/15.2 and
ARCHITECTURE.md 2.3's offline-first design:

1. **Idempotent creation** — a client-generated `client_ref` UUID on
   Meetings/Tasks, so replaying an offline-queued "create" twice (e.g.
   the app was killed before it saw the first response) never produces
   a duplicate.
2. **Optimistic-concurrency conflict detection** — an optional
   `client_updated_at` on Meeting/Task updates. A mismatch against the
   record's current `updated_at` returns 409 with the current server
   copy, which is exactly what ARCHITECTURE.md 2.3 asks for: "a manual
   merge prompt for conflicting task edits."
3. **A delta sync endpoint** (`GET /sync`) that returns everything a
   local cache needs to reconcile after reconnecting — updated meetings/
   tasks, and tombstones for deleted ones — in one call, backed by new
   soft-deletes on both tables.

**Nothing here touches `routes/api.php` as a full-file replacement** —
one route line is added via the snippet below. Every other file in this
delivery — including `MeetingController.php`, `TaskController.php`,
`MeetingService.php`, `TaskService.php`, and the `Meeting`/`Task` models
— is a **full replacement**; none of those are on your protected-files
list, so they're delivered complete rather than as diffs. No changes to
`app/Providers/AppServiceProvider.php` or `bootstrap/app.php` are needed
this phase — nothing new needs binding, and conflicts are caught locally
in the two controllers rather than mapped globally.

## New files (drop in as-is)
```
database/migrations/2024_09_01_000000_add_client_ref_to_meetings_and_tasks_tables.php
database/migrations/2024_09_01_000001_add_soft_deletes_to_meetings_and_tasks_tables.php
app/Exceptions/SyncConflictException.php
app/Http/Requests/Sync/SyncRequest.php
app/Services/SyncService.php
app/Http/Controllers/Api/V1/SyncController.php
tests/Feature/Meeting/MeetingSyncTest.php
tests/Feature/Task/TaskSyncTest.php
tests/Feature/Sync/SyncTest.php
```

## Modified files (full replacements — copy over the existing ones)
```
app/Models/Meeting.php                        — + client_ref fillable, SoftDeletes trait
app/Models/Task.php                           — + client_ref fillable, SoftDeletes trait
app/Repositories/Contracts/MeetingRepositoryInterface.php  — + findByClientRef
app/Repositories/Eloquent/MeetingRepository.php             — implements it
app/Repositories/Contracts/TaskRepositoryInterface.php      — + findByClientRef
app/Repositories/Eloquent/TaskRepository.php                 — implements it
app/Services/MeetingService.php               — idempotent create() + conflict-guarded update()
app/Services/TaskService.php                  — idempotent create() + conflict-guarded update()
app/Http/Requests/Meeting/StoreMeetingRequest.php   — + client_ref
app/Http/Requests/Meeting/UpdateMeetingRequest.php  — + client_updated_at
app/Http/Requests/Task/StoreTaskRequest.php         — + client_ref
app/Http/Requests/Task/UpdateTaskRequest.php        — + client_updated_at
app/Http/Controllers/Api/V1/MeetingController.php   — update() catches SyncConflictException → 409
app/Http/Controllers/Api/V1/TaskController.php      — update() catches SyncConflictException → 409
app/Http/Resources/MeetingResource.php        — + client_ref
app/Http/Resources/TaskResource.php           — + client_ref
```

## Integrate

1. Copy every new/modified file above into the matching path.
2. Apply the `routes/api.php` snippet below.
3. `php artisan migrate`.
4. `php artisan test`.

## Hand-merge snippet — `routes/api.php`

Add the import near the other `App\Http\Controllers\Api\V1\*` ones:

```php
use App\Http\Controllers\Api\V1\SyncController;
```

Add this route inside the existing `Route::middleware('auth:sanctum')->group(...)` block (anywhere is fine — e.g. right after the `/calendar` route, or after Phase 9's `/analytics` routes if you already added those):

```php
        // Phase 10 — offline/outbox delta sync (FR-15.2).
        Route::get('/sync', [SyncController::class, 'index'])
            ->name('api.v1.sync.index');
```

## Design notes

**`client_ref` is a UUID, not an auto-increment mirror.** The Flutter
outbox creates a Meeting/Task locally the instant the user acts, before
any network round trip. It needs an ID *now* to reference from other
local records (e.g. a locally-drafted task linked to a locally-drafted
meeting) — a client-generated UUID is the standard way to do that
without waiting on the server. `client_ref` is that UUID, stored
alongside (not instead of) the normal auto-increment `id` the server
still assigns. `(workspace_id, client_ref)` is uniquely indexed so two
different offline drafts can never collide, and `findByClientRef()`
looks it up `withTrashed()` so a very-delayed retry against an
already-deleted record still resolves instead of hitting the unique
constraint.

**Idempotent replay still returns 201, not 200.** `MeetingController::
store()` / `TaskController::store()` are unchanged in this respect —
whether `MeetingService::create()` actually inserted a new row or
returned an existing one keyed by `client_ref`, the response is still
"here's your created resource." A stricter REST purist might want 200
on replay; 201 keeps the controller simpler and the client doesn't need
to branch on it either way, since it's about to reconcile by `id`/
`client_ref` regardless.

**Conflict detection is opt-in per request, not a blanket lock.**
`guardAgainstConflict()` only runs when the caller actually sends
`client_updated_at`. An always-online client (or a future admin web
panel) that never sends it skips the check entirely and behaves exactly
as before this phase — this is additive, not a breaking change to the
update endpoints' existing contract.

**Why soft deletes, specifically for sync.** A hard delete gives a
reconnecting client no signal at all — the row is simply absent from
future responses, indistinguishable from "I never happened to fetch
it." `SyncService` needs an explicit tombstone list to tell the local
cache "remove ID 42," which requires the row (or at least its ID) to
still exist somewhere after "deletion." Every existing query is
unaffected: Eloquent's `SoftDeletes` trait excludes trashed rows by
default, so `MeetingController::index()`, `TaskController::index()`,
etc. all continue to hide deleted records without any changes to their
own code.

**Soft-deleting a Task no longer relies on the DB's `ON DELETE CASCADE`
for its comments/attachments.** Those cascade constraints only fire on
an actual row delete, which no longer happens. This is intentional, not
an oversight: the whole point of moving to soft-delete here is that the
row is a *tombstone*, not a purge — a task's comment history staying
in the database after a soft-delete is consistent with that, and
`TaskService::delete()` still explicitly removes the attachment *files*
from storage either way, so nothing is leaked. If a genuine
permanent-erasure ("hard delete for real, comments and all") feature is
wanted later, that's a separate, deliberate admin action — not
something this phase silently changes.

**Sync is scoped to meetings + tasks, matching PHASES.md's own
wording** ("Local caching strategy finalized across meetings/tasks").
Extending the same `upserts`/`deletes` shape to notifications or other
resources later is a straightforward follow-up, not a redesign.

**`server_time` is returned so the client never sets its own cursor.**
The response includes the server's clock reading at the moment the
query ran; the Flutter app should store *that* as the value to send back
as `since` on its next sync call, rather than its own device clock. This
avoids a whole category of missed-update bugs from clock skew between
the phone and the server.

## Deferred

- **Frontend** (Hive/Drift schema, the outbox queue itself, the merge-
  conflict UI that consumes the 409 response) is intentionally not part
  of this delivery, per the earlier decision to keep this phase
  backend-only.
- **Automatic conflict resolution** (e.g. field-level three-way merge)
  is out of scope — ARCHITECTURE.md 2.3 explicitly calls for a *manual*
  merge prompt for tasks, and this backend's job is only to detect and
  surface the conflict, not resolve it.
- **Extending `client_ref`/conflict detection to other mutable
  resources** (task comments, workspace files, etc.) wasn't requested by
  PHASES.md's Phase 10 wording and is deferred until a concrete offline
  use case for them shows up.
- **Purging old soft-deleted rows** (a scheduled `meetings:prune` /
  `tasks:prune` command) isn't included — nothing in this phase requires
  it yet, and adding it later is a small, independent addition once
  there's a real retention policy to enforce.

## Endpoints added
```
GET /api/v1/sync?workspace_id=&since=   (auth:sanctum — since omitted = full sync)
```

## Endpoints changed (backward-compatible additions only)
```
POST  /api/v1/meetings              — accepts optional `client_ref`
PUT   /api/v1/meetings/{meeting}    — accepts optional `client_updated_at`; 409 on conflict
DELETE /api/v1/meetings/{meeting}   — now soft-deletes
POST  /api/v1/tasks                 — accepts optional `client_ref`
PUT   /api/v1/tasks/{task}          — accepts optional `client_updated_at`; 409 on conflict
DELETE /api/v1/tasks/{task}         — now soft-deletes
```
