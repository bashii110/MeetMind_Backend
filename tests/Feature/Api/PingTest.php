<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class PingTest extends TestCase
{
    public function test_ping_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/ping');

        $response->assertOk()
            ->assertJsonPath('message', 'pong')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure(['message', 'data' => ['app', 'status', 'timestamp']]);
    }
}
