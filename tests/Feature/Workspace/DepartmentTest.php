<?php

namespace Tests\Feature\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_can_create_and_list_departments(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspace->id}/departments", ['name' => 'Engineering'])
            ->assertCreated();

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/v1/workspaces/{$workspace->id}/departments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Engineering');
    }

    public function test_a_regular_member_cannot_create_a_department(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => WorkspaceRole::Owner->value]);
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => WorkspaceRole::Member->value]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/v1/workspaces/{$workspace->id}/departments", ['name' => 'Sales'])
            ->assertStatus(403);
    }
}
