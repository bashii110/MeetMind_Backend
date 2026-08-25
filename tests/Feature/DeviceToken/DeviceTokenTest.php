<?php

namespace Tests\Feature\DeviceToken;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_a_device_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/device-tokens', [
            'token' => 'fcm-token-abc',
            'platform' => 'android',
            'device_name' => 'Pixel 8',
        ])->assertCreated();

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->id,
            'token' => 'fcm-token-abc',
            'platform' => 'android',
        ]);
    }

    public function test_registering_the_same_token_again_moves_it_to_the_new_owner(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->actingAs($first, 'sanctum')
            ->postJson('/api/v1/device-tokens', ['token' => 'shared-device'])
            ->assertCreated();

        $this->actingAs($second, 'sanctum')
            ->postJson('/api/v1/device-tokens', ['token' => 'shared-device'])
            ->assertCreated();

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertDatabaseHas('device_tokens', ['token' => 'shared-device', 'user_id' => $second->id]);
    }

    public function test_a_user_can_unregister_their_device_token(): void
    {
        $user = User::factory()->create();
        $user->deviceTokens()->create(['token' => 'to-remove']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/device-tokens', ['token' => 'to-remove'])
            ->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'to-remove']);
    }

    public function test_a_user_cannot_unregister_someone_elses_token(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $owner->deviceTokens()->create(['token' => 'not-yours']);

        $this->actingAs($attacker, 'sanctum')
            ->deleteJson('/api/v1/device-tokens', ['token' => 'not-yours'])
            ->assertOk(); // request succeeds but scoped delete matches nothing

        $this->assertDatabaseHas('device_tokens', ['token' => 'not-yours', 'user_id' => $owner->id]);
    }
}
