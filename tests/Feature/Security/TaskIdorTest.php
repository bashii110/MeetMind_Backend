<?php

namespace Tests\Feature\Security;

use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regression tests for two IDOR (Insecure Direct Object Reference)
 * findings from the Phase 11 security review — see the fix comments in
 * app/Http/Controllers/Api/V1/TaskController.php's destroyComment() and
 * destroyAttachment(). Before the fix, both endpoints authorized only
 * against the :task in the URL and never verified the :comment/
 * :attachment route-bound model actually belonged to it.
 */
class TaskIdorTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_an_attachment_cannot_be_removed_through_an_unrelated_task_url(): void
    {
        Storage::fake('local');

        [$manager, $workspaceA] = $this->userWithWorkspace();
        $taskA = Task::factory()->for($workspaceA)->for($manager, 'creator')->create();

        [$otherOwner, $workspaceB] = $this->userWithWorkspace();
        $taskB = Task::factory()->for($workspaceB)->for($otherOwner, 'creator')->create();

        $attachmentId = $this->actingAs($otherOwner, 'sanctum')->post("/api/v1/tasks/{$taskB->id}/attachments", [
            'file' => UploadedFile::fake()->create('secret.pdf', 10, 'application/pdf'),
        ])->assertCreated()->json('data.id');

        // $manager legitimately has "update" rights on taskA (their own
        // task) but is trying to delete taskB's attachment by guessing
        // its ID through taskA's URL — a task/attachment pairing that
        // was never actually authorized.
        $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/v1/tasks/{$taskA->id}/attachments/{$attachmentId}")
            ->assertStatus(404);

        $this->assertDatabaseHas('task_attachments', ['id' => $attachmentId]);
    }

    public function test_a_comment_cannot_be_deleted_through_an_unrelated_task_url(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $taskA = Task::factory()->for($workspace)->for($user, 'creator')->create();
        $taskB = Task::factory()->for($workspace)->for($user, 'creator')->create();

        $commentId = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/tasks/{$taskB->id}/comments", ['comment' => 'On task B'])
            ->assertCreated()->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/tasks/{$taskA->id}/comments/{$commentId}")
            ->assertStatus(404);

        $this->assertDatabaseHas('task_comments', ['id' => $commentId]);
    }
}
