<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceFile;
use App\Repositories\Contracts\WorkspaceFileRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** FR-10.5: shared files within a workspace. */
class WorkspaceFileService
{
    public function __construct(
        private readonly WorkspaceFileRepositoryInterface $files,
        private readonly ActivityLogService $activity,
    ) {}

    public function listForWorkspace(Workspace $workspace): Collection
    {
        return $this->files->forWorkspace($workspace);
    }

    public function upload(Workspace $workspace, User $uploader, UploadedFile $file): WorkspaceFile
    {
        $path = $file->store("workspace-files/{$workspace->id}", 'local');

        $workspaceFile = $this->files->create([
            'workspace_id' => $workspace->id,
            'uploaded_by' => $uploader->id,
            'file_path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        $this->activity->log($workspace, $uploader, ActivityAction::FileShared, $workspaceFile, [
            'filename' => $workspaceFile->original_filename,
        ]);

        return $workspaceFile;
    }

    public function delete(WorkspaceFile $file): void
    {
        Storage::disk('local')->delete($file->file_path);
        $this->files->delete($file);
    }
}
