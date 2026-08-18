<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ali Khan',
            'email' => 'ali@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'ali@example.com')
            ->assertJsonStructure(['data' => ['user', 'access_token', 'refresh_token', 'token_type']]);

        $this->assertDatabaseHas('users', ['email' => 'ali@example.com']);
    }

    public function test_a_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_a_disabled_account_cannot_login(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Password123!'),
            'is_disabled' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_an_authenticated_user_can_fetch_their_own_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/auth/me');

        $response->assertOk()->assertJsonPath('data.email', $user->email);
    }

    public function test_a_refresh_token_can_mint_a_new_access_token_but_an_access_token_cannot(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->json('data');

        // The access token must NOT be able to refresh.
        $this->withToken($login['access_token'])
            ->postJson('/api/v1/auth/refresh')
            ->assertStatus(403);

        // The refresh token must succeed.
        $this->withToken($login['refresh_token'])
            ->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token']]);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);

        $accessToken = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->json('data.access_token');

        $this->withToken($accessToken)->postJson('/api/v1/auth/logout')->assertOk();

        $this->withToken($accessToken)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/profile', [
            'bio' => 'Product manager who talks in bullet points.',
            'company' => 'MeetMind',
            'skills' => ['product', 'roadmapping'],
        ]);

        $response->assertOk()->assertJsonPath('data.bio', 'Product manager who talks in bullet points.');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'company' => 'MeetMind']);
    }
}
