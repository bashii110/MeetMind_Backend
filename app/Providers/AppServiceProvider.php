<?php

namespace App\Providers;

use App\Repositories\Contracts\AppNotificationRepositoryInterface;
use App\Repositories\Contracts\AudioFileRepositoryInterface;
use App\Repositories\Contracts\MeetingRepositoryInterface;
use App\Repositories\Contracts\SummaryRepositoryInterface;
use App\Repositories\Contracts\TagRepositoryInterface;
use App\Repositories\Contracts\TaskAttachmentRepositoryInterface;
use App\Repositories\Contracts\TaskCandidateRepositoryInterface;
use App\Repositories\Contracts\TaskCommentRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\TranscriptRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Repositories\Eloquent\AppNotificationRepository;
use App\Repositories\Eloquent\AudioFileRepository;
use App\Repositories\Eloquent\MeetingRepository;
use App\Repositories\Eloquent\SummaryRepository;
use App\Repositories\Eloquent\TagRepository;
use App\Repositories\Eloquent\TaskAttachmentRepository;
use App\Repositories\Eloquent\TaskCandidateRepository;
use App\Repositories\Eloquent\TaskCommentRepository;
use App\Repositories\Eloquent\TaskRepository;
use App\Repositories\Eloquent\TranscriptRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WorkspaceRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Bind each {Resource}RepositoryInterface to its Eloquent implementation
     * here as resources are added, e.g.:
     *
     *   $this->app->bind(
     *       \App\Repositories\Contracts\MeetingRepositoryInterface::class,
     *       \App\Repositories\Eloquent\MeetingRepository::class,
     *   );
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(WorkspaceRepositoryInterface::class, WorkspaceRepository::class);
        $this->app->bind(MeetingRepositoryInterface::class, MeetingRepository::class);
        $this->app->bind(TagRepositoryInterface::class, TagRepository::class);
        $this->app->bind(AppNotificationRepositoryInterface::class, AppNotificationRepository::class);
        $this->app->bind(AudioFileRepositoryInterface::class, AudioFileRepository::class);
        $this->app->bind(TranscriptRepositoryInterface::class, TranscriptRepository::class);
        $this->app->bind(SummaryRepositoryInterface::class, SummaryRepository::class);
        $this->app->bind(TaskCandidateRepositoryInterface::class, TaskCandidateRepository::class);
        $this->app->bind(TaskRepositoryInterface::class, TaskRepository::class);
        $this->app->bind(TaskCommentRepositoryInterface::class, TaskCommentRepository::class);
        $this->app->bind(TaskAttachmentRepositoryInterface::class, TaskAttachmentRepository::class);
    }

    public function boot(): void
{
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->ip());
    });

    RateLimiter::for('auth', function (Request $request) {
        return Limit::perMinute(10)->by($request->ip());
    });
}
}
