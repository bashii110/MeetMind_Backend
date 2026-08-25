<?php

namespace Tests\Feature\Notification;

use App\Enums\NotificationType;
use App\Jobs\SendPushNotificationJob;
use App\Models\User;
use App\Services\FcmService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_notification_queues_a_push_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        app(NotificationService::class)->notify($user, NotificationType::TaskAssigned, [
            'task_id' => 1,
            'task_title' => 'Write the report',
            'assigned_by_name' => 'Ali',
        ]);

        Queue::assertPushed(SendPushNotificationJob::class, fn ($job) => $job->user->is($user));
    }

    public function test_the_push_job_sends_to_every_registered_device_and_prunes_dead_tokens(): void
    {
        Cache::forget('fcm:access_token');
        config(['services.firebase.project_id' => 'meetmind-test']);
        config(['services.firebase.credentials' => $this->fakeServiceAccountPath()]);

        $user = User::factory()->create();
        $user->deviceTokens()->create(['token' => 'alive-token']);
        $user->deviceTokens()->create(['token' => 'dead-token']);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-access-token', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::sequence()
                ->push(['name' => 'projects/meetmind-test/messages/1'], 200)
                ->push(['error' => ['status' => 'UNREGISTERED']], 404),
        ]);

        app(FcmService::class)->sendToUser($user, 'Hello', 'World');

        $this->assertDatabaseHas('device_tokens', ['token' => 'alive-token']);
        $this->assertDatabaseMissing('device_tokens', ['token' => 'dead-token']);
    }

    /**
     * Generates a throwaway RSA key pair so FcmService's RS256 JWT
     * signing has something real to sign against, without needing an
     * actual Firebase project's service-account.json in the test suite.
     */
    private function fakeServiceAccountPath(): string
    {
        $path = storage_path('app/testing/fake-service-account.json');
        @mkdir(dirname($path), 0777, true);

        $keyPair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($keyPair, $privateKeyPem);

        file_put_contents($path, json_encode([
            'client_email' => 'test@meetmind-test.iam.gserviceaccount.com',
            'private_key' => $privateKeyPem,
        ]));

        return $path;
    }
}
