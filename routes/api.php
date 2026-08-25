<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\AudioFileController;
use App\Http\Controllers\Api\V1\CalendarController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\MeetingAiController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PingController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TaskCandidateController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\Api\V1\WorkspaceFileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (/api/v1/*)
|--------------------------------------------------------------------------
| Registered via bootstrap/app.php -> withRouting(api: __DIR__.'/../routes/api.php').
| All routes are versioned under v1 per ARCHITECTURE.md section 5.
*/

Route::prefix('v1')->group(function () {
    Route::get('/ping', PingController::class)->name('api.v1.ping');

    Route::prefix('auth')->name('api.v1.auth.')->group(function () {
        // Public, rate-limited (FR-1.x, ARCHITECTURE.md 6).
        Route::middleware('throttle:auth')->group(function () {
            Route::post('/register', [AuthController::class, 'register'])->name('register');
            Route::post('/login', [AuthController::class, 'login'])->name('login');
            Route::post('/google', [AuthController::class, 'loginWithGoogle'])->name('google');
            Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
            Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
        });

        // Signed link from VerifyEmailNotification — auth via signature, not a token.
        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware('signed')
            ->name('verify-email');

        // Requires a valid access OR refresh token (refresh checks the
        // token's ability itself, see AuthController::refresh).
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/refresh', [AuthController::class, 'refresh'])->name('refresh');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('/logout-all', [AuthController::class, 'logoutAllDevices'])->name('logout-all');
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])->name('resend-verification');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show'])->name('api.v1.profile.show');
        Route::post('/profile', [ProfileController::class, 'update'])->name('api.v1.profile.update');
        // POST (not PUT/PATCH) because avatar upload needs multipart/form-data,
        // which PHP doesn't parse for PUT/PATCH without extra middleware.

        // Phase 7 — full workspace CRUD, replacing the read-only index
        // that stood in until team management existed.
        Route::apiResource('workspaces', WorkspaceController::class)->names('api.v1.workspaces');
        Route::post('/workspaces/{workspace}/members', [WorkspaceController::class, 'inviteMember'])->name('api.v1.workspaces.members.store');
        Route::patch('/workspaces/{workspace}/members/{user}', [WorkspaceController::class, 'updateMember'])->name('api.v1.workspaces.members.update');
        Route::delete('/workspaces/{workspace}/members/{user}', [WorkspaceController::class, 'removeMember'])->name('api.v1.workspaces.members.destroy');
        Route::post('/workspaces/{workspace}/leave', [WorkspaceController::class, 'leave'])->name('api.v1.workspaces.leave');
        Route::get('/workspaces/{workspace}/activity', [WorkspaceController::class, 'activity'])->name('api.v1.workspaces.activity');

        Route::get('/workspaces/{workspace}/departments', [DepartmentController::class, 'index'])->name('api.v1.departments.index');
        Route::post('/workspaces/{workspace}/departments', [DepartmentController::class, 'store'])->name('api.v1.departments.store');
        Route::delete('/workspaces/{workspace}/departments/{department}', [DepartmentController::class, 'destroy'])->name('api.v1.departments.destroy');

        Route::get('/workspaces/{workspace}/files', [WorkspaceFileController::class, 'index'])->name('api.v1.workspace-files.index');
        Route::post('/workspaces/{workspace}/files', [WorkspaceFileController::class, 'store'])->name('api.v1.workspace-files.store');
        Route::delete('/workspaces/{workspace}/files/{file}', [WorkspaceFileController::class, 'destroy'])->name('api.v1.workspace-files.destroy');

        Route::apiResource('meetings', MeetingController::class)->names('api.v1.meetings');
        Route::patch('/meetings/{meeting}/status', [MeetingController::class, 'updateStatus'])->name('api.v1.meetings.status');
        Route::post('/meetings/{meeting}/participants', [MeetingController::class, 'inviteParticipants'])->name('api.v1.meetings.participants.store');
        Route::delete('/meetings/{meeting}/participants/{user}', [MeetingController::class, 'removeParticipant'])->name('api.v1.meetings.participants.destroy');
        Route::post('/meetings/{meeting}/participants/respond', [MeetingController::class, 'respondToInvitation'])->name('api.v1.meetings.participants.respond');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('api.v1.notifications.index');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('api.v1.notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('api.v1.notifications.read');

        Route::post('/meetings/{meeting}/recording/init', [AudioFileController::class, 'init'])->name('api.v1.audio.init');
        Route::post('/audio-files/{audioFile}/chunks', [AudioFileController::class, 'storeChunk'])->name('api.v1.audio.chunks');
        Route::get('/audio-files/{audioFile}/status', [AudioFileController::class, 'status'])->name('api.v1.audio.status');
        Route::post('/audio-files/{audioFile}/complete', [AudioFileController::class, 'complete'])->name('api.v1.audio.complete');

        Route::get('/meetings/{meeting}/ai-status', [MeetingAiController::class, 'aiStatus'])->name('api.v1.meetings.ai-status');
        Route::get('/meetings/{meeting}/transcript', [MeetingAiController::class, 'transcript'])->name('api.v1.meetings.transcript');
        Route::get('/meetings/{meeting}/summary', [MeetingAiController::class, 'summary'])->name('api.v1.meetings.summary');
        Route::get('/meetings/{meeting}/task-candidates', [MeetingAiController::class, 'taskCandidates'])->name('api.v1.meetings.task-candidates');
        Route::post('/task-candidates/{taskCandidate}/confirm', [TaskCandidateController::class, 'confirm'])->name('api.v1.task-candidates.confirm');
        Route::post('/task-candidates/{taskCandidate}/dismiss', [TaskCandidateController::class, 'dismiss'])->name('api.v1.task-candidates.dismiss');

        Route::apiResource('tasks', TaskController::class)->names('api.v1.tasks');
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('api.v1.tasks.status');
        Route::patch('/tasks/{task}/progress', [TaskController::class, 'updateProgress'])->name('api.v1.tasks.progress');
        Route::patch('/tasks/{task}/assign', [TaskController::class, 'assign'])->name('api.v1.tasks.assign');
        Route::post('/tasks/{task}/comments', [TaskController::class, 'storeComment'])->name('api.v1.tasks.comments.store');
        Route::delete('/tasks/{task}/comments/{comment}', [TaskController::class, 'destroyComment'])->name('api.v1.tasks.comments.destroy');
        Route::post('/tasks/{task}/attachments', [TaskController::class, 'storeAttachment'])->name('api.v1.tasks.attachments.store');
        Route::delete('/tasks/{task}/attachments/{attachment}', [TaskController::class, 'destroyAttachment'])->name('api.v1.tasks.attachments.destroy');

        // Phase 6 — FCM push registration and calendar aggregation.
        Route::post('/device-tokens', [DeviceTokenController::class, 'store'])->name('api.v1.device-tokens.store');
        Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy'])->name('api.v1.device-tokens.destroy');

        Route::get('/calendar', [CalendarController::class, 'index'])->name('api.v1.calendar.index');
    });
});
