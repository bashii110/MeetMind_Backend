# Phase 7 Backend — Team Collaboration & Workspaces

Delivers PHASES.md Phase 7's backend: full workspace CRUD, member/role
management, an activity timeline, @mentions in task comments, shared
workspace files, and a lightweight optional departments sub-structure.

Builds on the Phase 5 backend + the Phase 6 backend zip delivered
earlier in this thread. Same delivery shape as Phase 6: new files plus a
short list of full-file replacements.

## Integrate

1. Copy this zip's `app/`, `database/`, `routes/`, and `tests/` over your
   tree (Phase 5 + Phase 6 already applied), overwriting the modified
   files listed below.
2. `php artisan migrate` — adds `activity_logs`, `departments`,
   `workspace_files`, and `workspace_members.department_id`.
3. No new env vars or config needed.
4. `php artisan test`.

## New files
```
database/migrations/2024_07_01_000000_create_activity_logs_table.php
database/migrations/2024_07_01_000001_create_departments_table.php
database/migrations/2024_07_01_000002_add_department_id_to_workspace_members_table.php
database/migrations/2024_07_01_000003_create_workspace_files_table.php
app/Enums/ActivityAction.php
app/Models/ActivityLog.php
app/Models/WorkspaceFile.php
app/Models/Department.php
app/Repositories/Contracts/ActivityLogRepositoryInterface.php
app/Repositories/Eloquent/ActivityLogRepository.php
app/Repositories/Contracts/WorkspaceFileRepositoryInterface.php
app/Repositories/Eloquent/WorkspaceFileRepository.php
app/Services/ActivityLogService.php
app/Services/WorkspaceFileService.php
app/Events/CommentMentioned.php
app/Listeners/CreateMentionNotification.php
app/Policies/WorkspacePolicy.php                      — auto-discovered by Laravel, no manual registration needed
app/Http/Requests/Workspace/StoreWorkspaceRequest.php
app/Http/Requests/Workspace/UpdateWorkspaceRequest.php
app/Http/Requests/Workspace/InviteWorkspaceMemberRequest.php
app/Http/Requests/Workspace/UpdateWorkspaceMemberRequest.php
app/Http/Requests/Workspace/StoreWorkspaceFileRequest.php
app/Http/Requests/Workspace/StoreDepartmentRequest.php
app/Http/Resources/WorkspaceMemberResource.php
app/Http/Resources/ActivityLogResource.php
app/Http/Resources/WorkspaceFileResource.php
app/Http/Resources/DepartmentResource.php
app/Http/Controllers/Api/V1/DepartmentController.php
app/Http/Controllers/Api/V1/WorkspaceFileController.php
tests/Feature/Workspace/WorkspaceTest.php
tests/Feature/Workspace/ActivityLogTest.php
tests/Feature/Workspace/WorkspaceFileTest.php
tests/Feature/Workspace/DepartmentTest.php
tests/Feature/Task/TaskMentionTest.php
```

## Modified files (full replacements)
```
app/Models/Workspace.php                     — + memberships/activityLogs/files/departments relations
app/Models/WorkspaceMember.php               — + department_id fillable + department() relation
app/Services/WorkspaceService.php            — + create/update/delete/inviteMember/updateMemberRole/removeMember/leave
app/Services/MeetingService.php              — + ActivityLogService; logs on create + status change (with actor)
app/Services/TaskService.php                 — + ActivityLogService; logs on create/assign/status change; addComment() now takes mentioned_user_ids
app/Http/Requests/Task/StoreTaskCommentRequest.php  — + mentioned_user_ids validation
app/Http/Resources/WorkspaceResource.php     — + member_count/members/departments
app/Http/Controllers/Api/V1/WorkspaceController.php — full CRUD + member management + activity endpoint (was index-only)
app/Http/Controllers/Api/V1/MeetingController.php   — updateStatus() now passes the acting user through
app/Http/Controllers/Api/V1/TaskController.php      — updateStatus() passes actor; storeComment() forwards mentions
app/Providers/AppServiceProvider.php         — binds the two new repository interfaces
routes/api.php                               — full workspace/member/activity/department/file routes
```

## Design notes

**Workspace invites are immediate, not pending.** Unlike meeting
participants (which have an accept/decline flow), adding someone to a
workspace attaches them right away and just notifies them
(`NotificationType::WorkspaceInvitation`). A workspace admin adding a
teammate is closer to "granting access" than "requesting a response" —
building a second pending-invitation state machine for this didn't earn
its complexity. Straightforward to add later if a real accept/decline
need shows up.

**Activity logging is a direct service call, not an event.** Every other
notification-producing side effect in this codebase goes through
Events+Listeners because it has a genuinely separate concern (push,
email) worth decoupling. The activity timeline has exactly one consumer
today, so `ActivityLogService::log()` is called directly from
`MeetingService`, `TaskService`, and `WorkspaceService` at the point of
action — same shape as `NotificationService::notify()`, just without the
extra event indirection that isn't paying for itself yet.

**@mentions trust the frontend's autocomplete, not text parsing.**
`StoreTaskCommentRequest` takes an explicit `mentioned_user_ids` array
and validates every ID against workspace membership. The comment text
itself is stored as-is. Re-detecting "@Name" substrings server-side is
fragile (ambiguous or partial name matches) and the autocomplete UI
already knows exactly which user was selected — trusting that is both
simpler and more correct. `CommentMentioned` → `CreateMentionNotification`
follows the same Event/Listener pattern as `TaskAssigned`/`TaskCompleted`.

**Departments are intentionally thin.** PHASES.md marks this
sub-structure optional, so `DepartmentController` skips the
repository/service layers used everywhere else in favor of direct
Eloquent calls in a small controller — not worth the same ceremony as a
required resource.

**Owner protections.** The workspace owner's role can't be changed,
they can't be removed by another manager, and they can't leave their own
workspace (only delete it) — `WorkspaceService` enforces all three via
`ValidationException`, left to `bootstrap/app.php`'s existing global
handler rather than caught per-controller-action, same pattern as
`AudioUploadService`.

## Endpoints added
```
POST   /api/v1/workspaces                              (auth:sanctum)
GET    /api/v1/workspaces/{workspace}                   (auth:sanctum)
PUT    /api/v1/workspaces/{workspace}                   (auth:sanctum, manager only)
DELETE /api/v1/workspaces/{workspace}                   (auth:sanctum, owner only)
POST   /api/v1/workspaces/{workspace}/members            (auth:sanctum, manager only — { email, role? })
PATCH  /api/v1/workspaces/{workspace}/members/{user}      (auth:sanctum, manager only — { role?, department_id? })
DELETE /api/v1/workspaces/{workspace}/members/{user}      (auth:sanctum, manager only)
POST   /api/v1/workspaces/{workspace}/leave               (auth:sanctum)
GET    /api/v1/workspaces/{workspace}/activity            (auth:sanctum, any member)

GET    /api/v1/workspaces/{workspace}/departments         (auth:sanctum, any member)
POST   /api/v1/workspaces/{workspace}/departments         (auth:sanctum, manager only)
DELETE /api/v1/workspaces/{workspace}/departments/{department} (auth:sanctum, manager only)

GET    /api/v1/workspaces/{workspace}/files                (auth:sanctum, any member)
POST   /api/v1/workspaces/{workspace}/files                (auth:sanctum, any member, multipart)
DELETE /api/v1/workspaces/{workspace}/files/{file}          (auth:sanctum, uploader or manager)
```

`POST /api/v1/tasks/{task}/comments` also now accepts an optional
`mentioned_user_ids: int[]` alongside the existing `comment` field.

## Deferred
Nothing pushed to a later phase this time — Phase 7's full scope (FR-10.1
through FR-10.5) is covered, with departments kept deliberately minimal
per its "optional" marking in PHASES.md.
