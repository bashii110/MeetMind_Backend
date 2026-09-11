<?php

namespace Tests\Feature\Security;

use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These tests exercise the rate limits added in the routes/api.php and
 * app/Providers/AppServiceProvider.php snippets from PHASE_11_NOTES.md —
 * they will fail with 201/200 on the final request instead of 429 until
 * those snippets have been applied.
 */
class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    private function userWithWorkspace(): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => WorkspaceRole::Owner->value]);

        return [$user, $workspace];
    }

    public function test_reporting_content_is_rate_limited(): void
    {
        [$user, $workspace] = $this->userWithWorkspace();
        $task = Task::factory()->for($workspace)->for($user, 'creator')->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports', [
                'subject_type' => 'task',
                'subject_id' => $task->id,
                'reason' => "Attempt {$i}",
            ])->assertCreated();
        }

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/reports', [
            'subject_type' => 'task',
            'subject_id' => $task->id,
            'reason' => 'One too many',
        ])->assertStatus(429);
    }

    public function test_resending_the_verification_email_is_rate_limited(): void
    {
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/email/resend')->assertOk();
        }

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/auth/email/resend')->assertStatus(429);
    }
}
