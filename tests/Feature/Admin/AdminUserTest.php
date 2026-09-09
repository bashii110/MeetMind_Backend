<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_system_admin_can_list_users(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);
        User::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/users');

        $response->assertOk()->assertJsonCount(4, 'data.items'); // 3 + the admin themself
    }

    public function test_a_regular_user_cannot_access_the_admin_users_endpoint(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/users')
            ->assertStatus(403);
    }

    public function test_a_system_admin_can_disable_and_re_enable_an_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);
        $target = User::factory()->create(['password' => bcrypt('Password123!')]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$target->id}/disable")
            ->assertOk()
            ->assertJsonPath('data.id', $target->id);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_disabled' => true]);

        // A disabled account cannot log in (AuthService::guardAgainstDisabledAccount).
        $this->postJson('/api/v1/auth/login', [
            'email' => $target->email,
            'password' => 'Password123!',
        ])->assertStatus(422);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/users/{$target->id}/enable")
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_disabled' => false]);
    }

    public function test_the_admin_overview_endpoint_reports_platform_totals(): void
    {
        $admin = User::factory()->create(['role' => UserRole::SystemAdmin]);
        User::factory()->create(['is_disabled' => true]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/overview');

        $response->assertOk()
            ->assertJsonPath('data.users.total', 2)
            ->assertJsonPath('data.users.disabled', 1)
            ->assertJsonStructure(['data' => ['storage', 'pending_reports', 'workspaces']]);
    }
}
