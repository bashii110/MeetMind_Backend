<?php

namespace Tests\Feature\Task;

use App\Enums\WorkspaceRole;
use App\Models\AudioFile;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\TaskCandidate;
use App\Models\Transcript;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_a_workspace_member_can_create_a_task(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/tasks', [
            'workspace_id' => $workspace->id,
            'title' => 'Finish onboarding flow',
            'priority' => 'high',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Finish onboarding flow')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('tasks', ['title' => 'Finish onboarding flow', 'workspace_id' => $workspace->id]);
    }

    public function test_the_task_list_is_scoped_to_the_users_workspaces(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        [, $otherWorkspace] = $this->userWithWorkspace();

        Task::factory()->for($workspace)->for($user, 'creator')->create(['title' => 'Mine']);
        Task::factory()->for($otherWorkspace)->create(['title' => 'Not mine']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks');

        $response->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertSame('Mine', $response->json('data.items.0.title'));
    }

    public function test_assigning_a_task_notifies_the_assignee(): void
    {
        [$owner, $workspace] = $this->userWithWorkspace();
        $assignee = User::factory()->create();
        $workspace->members()->attach($assignee->id, ['role' => WorkspaceRole::Member->value]);

        $task = Task::factory()->for($workspace)->for($owner, 'creator')->create();

        $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/v1/tasks/{$task->id}/assign", ['assigned_user_id' => $assignee->id])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $assignee->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $assignee->id,
            'type' => 'task_assigned',
        ]);
    }

    public function test_a_stranger_cannot_update_someone_elses_task(): void
    {
        [$owner, $workspace] = $this->userWithWorkspace();
        $task = Task::factory()->for($workspace)->for($owner, 'creator')->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger, 'sanctum')
            ->putJson("/api/v1/tasks/{$task->id}", ['title' => 'Hijacked'])
            ->assertStatus(403);
    }

    public function test_progress_is_clamped_to_0_100_and_status_is_independent(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $task = Task::factory()->for($workspace)->for($user, 'creator')->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/tasks/{$task->id}/progress", ['progress' => 100])
            ->assertOk()
            ->assertJsonPath('data.progress', 100)
            ->assertJsonPath('data.status', 'pending'); // unchanged — status and progress are independent
    }

    public function test_comments_can_be_added_and_deleted_by_their_author_only(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $task = Task::factory()->for($workspace)->for($user, 'creator')->create();
        $other = User::factory()->create();
        $workspace->members()->attach($other->id, ['role' => WorkspaceRole::Member->value]);

        $commentId = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/tasks/{$task->id}/comments", ['comment' => 'Looks good.'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/v1/tasks/{$task->id}/comments/{$commentId}")
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/tasks/{$task->id}/comments/{$commentId}")
            ->assertOk();

        $this->assertDatabaseMissing('task_comments', ['id' => $commentId]);
    }

    public function test_an_attachment_can_be_uploaded_and_removed(): void
    {
        Storage::fake('local');
        [$user, $workspace] = $this->userWithWorkspace();
        $task = Task::factory()->for($workspace)->for($user, 'creator')->create();

        $response = $this->actingAs($user, 'sanctum')->post("/api/v1/tasks/{$task->id}/attachments", [
            'file' => UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf'),
        ]);

        $response->assertCreated()->assertJsonPath('data.original_filename', 'spec.pdf');
        $attachmentId = $response->json('data.id');

        $this->assertDatabaseHas('task_attachments', ['id' => $attachmentId, 'task_id' => $task->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/tasks/{$task->id}/attachments/{$attachmentId}")
            ->assertOk();

        $this->assertDatabaseMissing('task_attachments', ['id' => $attachmentId]);
    }

    public function test_confirming_a_task_candidate_creates_a_real_task_and_allows_overrides(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $meeting = Meeting::factory()->for($workspace)->for($user, 'owner')->create();

        $audioFile = AudioFile::query()->create([
            'meeting_id' => $meeting->id,
            'uploaded_by' => $user->id,
            'status' => 'summarized',
            'extension' => 'm4a',
        ]);

        $transcript = Transcript::query()->create([
            'meeting_id' => $meeting->id,
            'audio_file_id' => $audioFile->id,
            'text' => 'Some transcript text.',
        ]);

        $candidate = TaskCandidate::query()->create([
            'meeting_id' => $meeting->id,
            'transcript_id' => $transcript->id,
            'title' => 'Draft the proposal',
            'suggested_priority' => 'medium',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson(
            "/api/v1/task-candidates/{$candidate->id}/confirm",
            ['priority' => 'high'], // override the AI's suggestion
        );

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Draft the proposal')
            ->assertJsonPath('data.priority', 'high');

        $candidate->refresh();
        $this->assertSame('confirmed', $candidate->status->value);
        $this->assertNotNull($candidate->confirmed_task_id);

        // Confirming twice should be rejected.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/task-candidates/{$candidate->id}/confirm")
            ->assertStatus(422);
    }

    public function test_the_reminder_command_notifies_assignees_of_tasks_due_within_24_hours_once(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();

        $dueSoon = Task::factory()->for($workspace)->for($user, 'creator')->create([
            'assigned_user_id' => $user->id,
            'deadline' => now()->addHours(6),
            'status' => 'pending',
        ]);
        $dueFar = Task::factory()->for($workspace)->for($user, 'creator')->create([
            'assigned_user_id' => $user->id,
            'deadline' => now()->addDays(10),
            'status' => 'pending',
        ]);
        $alreadyDone = Task::factory()->for($workspace)->for($user, 'creator')->create([
            'assigned_user_id' => $user->id,
            'deadline' => now()->addHours(3),
            'status' => 'completed',
        ]);

        $this->artisan('tasks:send-reminders')->assertSuccessful();

        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'deadline']);
        $this->assertDatabaseCount('notifications', 1); // only $dueSoon qualifies

        $dueSoon->refresh();
        $this->assertNotNull($dueSoon->last_reminder_sent_at);

        // Running again immediately shouldn't double-notify.
        $this->artisan('tasks:send-reminders')->assertSuccessful();
        $this->assertDatabaseCount('notifications', 1);

        $this->assertTrue($dueFar->deadline->isFuture());
        $this->assertSame('completed', $alreadyDone->status->value);
    }
}
