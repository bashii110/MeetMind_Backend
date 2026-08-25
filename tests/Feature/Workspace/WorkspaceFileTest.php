<?php

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkspaceFileTest extends TestCase
{
    use RefreshDatabase;

    private function ownerWithWorkspace(): array
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);

        return [$owner, $workspace];
    }

    public function test_a_member_can_upload_and_list_a_workspace_file(): void
    {
        Storage::fake('local');
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

        $upload = $this->actingAs($member, 'sanctum')->post("/api/v1/workspaces/{$workspace->id}/files", [
            'file' => UploadedFile::fake()->create('roadmap.pdf', 200, 'application/pdf'),
        ]);
        $upload->assertCreated()->assertJsonPath('data.original_filename', 'roadmap.pdf');

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspace->id}/files")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_only_the_uploader_or_a_manager_can_delete_a_file(): void
    {
        Storage::fake('local');
        [$owner, $workspace] = $this->ownerWithWorkspace();
        $uploader = User::factory()->create();
        $otherMember = User::factory()->create();
        $workspace->members()->attach($uploader->id, ['role' => WorkspaceRole::Member->value]);
        $workspace->members()->attach($otherMember->id, ['role' => WorkspaceRole::Member->value]);

        $fileId = $this->actingAs($uploader, 'sanctum')->post("/api/v1/workspaces/{$workspace->id}/files", [
            'file' => UploadedFile::fake()->create('notes.pdf', 50, 'application/pdf'),
        ])->json('data.id');

        $this->actingAs($otherMember, 'sanctum')
            ->deleteJson("/api/v1/workspaces/{$workspace->id}/files/{$fileId}")
            ->assertStatus(403);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/v1/workspaces/{$workspace->id}/files/{$fileId}")
            ->assertOk();

        $this->assertDatabaseMissing('workspace_files', ['id' => $fileId]);
    }
}
