<?php

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\FcmService;
use App\Support\NotificationCopy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers the push half of an already-created AppNotification (see
 * NotificationService::notify). Kept as its own queued job — on the
 * 'notifications' queue alongside the rest of Phase 2/5's notification
 * listeners — so a slow or failing FCM call never delays the in-app
 * notification row itself, and a push failure can retry independently.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public string $queue = 'notifications';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly User $user,
        public readonly NotificationType $type,
        public readonly array $payload,
    ) {}

    /**
     * @return array<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(FcmService $fcm): void
    {
        [$title, $body] = NotificationCopy::for($this->type, $this->payload);

        $fcm->sendToUser($this->user, $title, $body, [
            'type' => $this->type->value,
            ...$this->stringifiedPayload(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function stringifiedPayload(): array
    {
        return array_map(
            static fn ($value) => is_scalar($value) ? (string) $value : (string) json_encode($value),
            $this->payload,
        );
    }
}
