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

Route::prefix('v1')->group(function () {

    Route::get('/ping', PingController::class)
        ->name('api.v1.ping');

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->name('api.v1.auth.')->group(function () {

        Route::middleware('throttle:auth')->group(function () {

            Route::post('/register', [AuthController::class, 'register'])
                ->name('register');

            Route::post('/login', [AuthController::class, 'login'])
                ->name('login');

            Route::post('/google', [AuthController::class, 'loginWithGoogle'])
                ->name('google');

            Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
                ->name('forgot-password');

            Route::post('/reset-password', [AuthController::class, 'resetPassword'])
                ->name('reset-password');
        });

        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware('signed')
            ->name('verify-email');

        Route::middleware('auth:sanctum')->group(function () {

            Route::post('/refresh', [AuthController::class, 'refresh'])
                ->name('refresh');

            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('logout');

            Route::post('/logout-all', [AuthController::class, 'logoutAllDevices'])
                ->name('logout-all');

            Route::get('/me', [AuthController::class, 'me'])
                ->name('me');

            Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
                ->name('resend-verification');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Authenticated API
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Profile
        |--------------------------------------------------------------------------
        */

        Route::get('/profile', [ProfileController::class, 'show'])
            ->name('api.v1.profile.show');

        Route::post('/profile', [ProfileController::class, 'update'])
            ->name('api.v1.profile.update');

        /*
        |--------------------------------------------------------------------------
        | Workspaces — Phase 7
        |--------------------------------------------------------------------------
        */

        Route::apiResource('workspaces', WorkspaceController::class)
            ->names('api.v1.workspaces');

        // Workspace members
        Route::get(
            '/workspaces/{workspace}/members',
            [WorkspaceController::class, 'members']
        )->name('api.v1.workspaces.members.index');

        Route::post(
            '/workspaces/{workspace}/members',
            [WorkspaceController::class, 'inviteMember']
        )->name('api.v1.workspaces.members.store');

        Route::patch(
            '/workspaces/{workspace}/members/{user}',
            [WorkspaceController::class, 'updateMember']
        )->name('api.v1.workspaces.members.update');

        Route::delete(
            '/workspaces/{workspace}/members/{user}',
            [WorkspaceController::class, 'removeMember']
        )->name('api.v1.workspaces.members.destroy');

        Route::post(
            '/workspaces/{workspace}/leave',
            [WorkspaceController::class, 'leave']
        )->name('api.v1.workspaces.leave');

        Route::get(
            '/workspaces/{workspace}/activity',
            [WorkspaceController::class, 'activity']
        )->name('api.v1.workspaces.activity');

        /*
        |--------------------------------------------------------------------------
        | Departments
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/workspaces/{workspace}/departments',
            [DepartmentController::class, 'index']
        )->name('api.v1.departments.index');

        Route::post(
            '/workspaces/{workspace}/departments',
            [DepartmentController::class, 'store']
        )->name('api.v1.departments.store');

        Route::delete(
            '/workspaces/{workspace}/departments/{department}',
            [DepartmentController::class, 'destroy']
        )->name('api.v1.departments.destroy');

        /*
        |--------------------------------------------------------------------------
        | Workspace Files
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/workspaces/{workspace}/files',
            [WorkspaceFileController::class, 'index']
        )->name('api.v1.workspace-files.index');

        Route::post(
            '/workspaces/{workspace}/files',
            [WorkspaceFileController::class, 'store']
        )->name('api.v1.workspace-files.store');

        Route::delete(
            '/workspaces/{workspace}/files/{file}',
            [WorkspaceFileController::class, 'destroy']
        )->name('api.v1.workspace-files.destroy');

        /*
        |--------------------------------------------------------------------------
        | Meetings
        |--------------------------------------------------------------------------
        */

        Route::apiResource('meetings', MeetingController::class)
            ->names('api.v1.meetings');

        Route::patch(
            '/meetings/{meeting}/status',
            [MeetingController::class, 'updateStatus']
        )->name('api.v1.meetings.status');

        Route::post(
            '/meetings/{meeting}/participants',
            [MeetingController::class, 'inviteParticipants']
        )->name('api.v1.meetings.participants.store');

        Route::delete(
            '/meetings/{meeting}/participants/{user}',
            [MeetingController::class, 'removeParticipant']
        )->name('api.v1.meetings.participants.destroy');

        Route::post(
            '/meetings/{meeting}/participants/respond',
            [MeetingController::class, 'respondToInvitation']
        )->name('api.v1.meetings.participants.respond');

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/notifications',
            [NotificationController::class, 'index']
        )->name('api.v1.notifications.index');

        Route::post(
            '/notifications/read-all',
            [NotificationController::class, 'markAllRead']
        )->name('api.v1.notifications.read-all');

        Route::post(
            '/notifications/{notification}/read',
            [NotificationController::class, 'markRead']
        )->name('api.v1.notifications.read');

        /*
        |--------------------------------------------------------------------------
        | Audio / Recordings
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/meetings/{meeting}/recording/init',
            [AudioFileController::class, 'init']
        )->name('api.v1.audio.init');

        Route::post(
            '/audio-files/{audioFile}/chunks',
            [AudioFileController::class, 'storeChunk']
        )->name('api.v1.audio.chunks');

        Route::get(
            '/audio-files/{audioFile}/status',
            [AudioFileController::class, 'status']
        )->name('api.v1.audio.status');

        Route::post(
            '/audio-files/{audioFile}/complete',
            [AudioFileController::class, 'complete']
        )->name('api.v1.audio.complete');

        /*
        |--------------------------------------------------------------------------
        | Meeting AI
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/meetings/{meeting}/ai-status',
            [MeetingAiController::class, 'aiStatus']
        )->name('api.v1.meetings.ai-status');

        Route::get(
            '/meetings/{meeting}/transcript',
            [MeetingAiController::class, 'transcript']
        )->name('api.v1.meetings.transcript');

        Route::get(
            '/meetings/{meeting}/summary',
            [MeetingAiController::class, 'summary']
        )->name('api.v1.meetings.summary');

        Route::get(
            '/meetings/{meeting}/task-candidates',
            [MeetingAiController::class, 'taskCandidates']
        )->name('api.v1.meetings.task-candidates');

        Route::post(
            '/task-candidates/{taskCandidate}/confirm',
            [TaskCandidateController::class, 'confirm']
        )->name('api.v1.task-candidates.confirm');

        Route::post(
            '/task-candidates/{taskCandidate}/dismiss',
            [TaskCandidateController::class, 'dismiss']
        )->name('api.v1.task-candidates.dismiss');

        /*
        |--------------------------------------------------------------------------
        | Tasks
        |--------------------------------------------------------------------------
        */

        Route::apiResource('tasks', TaskController::class)
            ->names('api.v1.tasks');

        Route::patch(
            '/tasks/{task}/status',
            [TaskController::class, 'updateStatus']
        )->name('api.v1.tasks.status');

        Route::patch(
            '/tasks/{task}/progress',
            [TaskController::class, 'updateProgress']
        )->name('api.v1.tasks.progress');

        Route::patch(
            '/tasks/{task}/assign',
            [TaskController::class, 'assign']
        )->name('api.v1.tasks.assign');

        Route::post(
            '/tasks/{task}/comments',
            [TaskController::class, 'storeComment']
        )->name('api.v1.tasks.comments.store');

        Route::delete(
            '/tasks/{task}/comments/{comment}',
            [TaskController::class, 'destroyComment']
        )->name('api.v1.tasks.comments.destroy');

        Route::post(
            '/tasks/{task}/attachments',
            [TaskController::class, 'storeAttachment']
        )->name('api.v1.tasks.attachments.store');

        Route::delete(
            '/tasks/{task}/attachments/{attachment}',
            [TaskController::class, 'destroyAttachment']
        )->name('api.v1.tasks.attachments.destroy');

        /*
        |--------------------------------------------------------------------------
        | FCM Device Tokens
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/device-tokens',
            [DeviceTokenController::class, 'store']
        )->name('api.v1.device-tokens.store');

        Route::delete(
            '/device-tokens',
            [DeviceTokenController::class, 'destroy']
        )->name('api.v1.device-tokens.destroy');

        /*
        |--------------------------------------------------------------------------
        | Calendar
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/calendar',
            [CalendarController::class, 'index']
        )->name('api.v1.calendar.index');
    });
});

