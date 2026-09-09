<?php

namespace App\Services;

use App\Enums\ContentReportStatus;
use App\Models\AudioFile;
use App\Models\ReportedContent;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * FR-16.1/16.2: account management and the platform-wide storage/usage
 * figures shown on the Admin Dashboard (DESIGN.md 3.13).
 */
class AdminService
{
    /**
     * @param array{search?: string, is_disabled?: bool} $filters
     */
    public function listUsers(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        /** @var Builder $query */
        $query = User::query();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if (array_key_exists('is_disabled', $filters) && $filters['is_disabled'] !== null) {
            $query->where('is_disabled', (bool) $filters['is_disabled']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /** Revokes all of the user's tokens too, so a disabled account is signed out everywhere immediately. */
    public function disable(User $user): User
    {
        $user->update(['is_disabled' => true]);
        $user->tokens()->delete();

        return $user->fresh();
    }

    public function enable(User $user): User
    {
        $user->update(['is_disabled' => false]);

        return $user->fresh();
    }

    /**
     * @return array{audio_bytes: int, task_attachments_bytes: int, workspace_files_bytes: int, total_bytes: int}
     */
    public function storageUsage(): array
    {
        // Summed from the size columns already recorded at upload time
        // (see AudioUploadService::complete / TaskService::addAttachment /
        // WorkspaceFileService::upload) rather than walking the disk —
        // cheap regardless of how much has been uploaded.
        $audio = (int) AudioFile::query()->sum('total_size');
        $attachments = (int) TaskAttachment::query()->sum('size');
        $workspaceFiles = (int) WorkspaceFile::query()->sum('size');

        return [
            'audio_bytes' => $audio,
            'task_attachments_bytes' => $attachments,
            'workspace_files_bytes' => $workspaceFiles,
            'total_bytes' => $audio + $attachments + $workspaceFiles,
        ];
    }

    /**
     * @return array{users: array{total: int, disabled: int}, workspaces: int, storage: array, pending_reports: int}
     */
    public function overview(): array
    {
        return Cache::remember('admin:overview', now()->addMinutes(5), function () {
            return [
                'users' => [
                    'total' => User::query()->count(),
                    'disabled' => User::query()->where('is_disabled', true)->count(),
                ],
                'workspaces' => Workspace::query()->count(),
                'storage' => $this->storageUsage(),
                'pending_reports' => ReportedContent::query()
                    ->where('status', ContentReportStatus::Pending->value)
                    ->count(),
            ];
        });
    }
}
