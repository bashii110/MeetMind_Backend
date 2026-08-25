<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceToken\RegisterDeviceTokenRequest;
use App\Http\Requests\DeviceToken\UnregisterDeviceTokenRequest;
use App\Repositories\Contracts\DeviceTokenRepositoryInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * FR-9.1 push delivery targets. The Flutter client registers on app
 * start and whenever Firebase rotates the token, and unregisters on
 * logout so a signed-out device stops receiving pushes for an account
 * it's no longer signed into (frontend: AuthController.logout()).
 */
class DeviceTokenController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly DeviceTokenRepositoryInterface $deviceTokens) {}

    public function store(RegisterDeviceTokenRequest $request): JsonResponse
    {
        $this->deviceTokens->upsertForUser(
            $request->user(),
            $request->string('token')->toString(),
            $request->input('platform'),
            $request->input('device_name'),
        );

        return $this->success(null, 'Device registered for push notifications.', 201);
    }

    public function destroy(UnregisterDeviceTokenRequest $request): JsonResponse
    {
        $this->deviceTokens->deleteByToken($request->user(), $request->string('token')->toString());

        return $this->success(null, 'Device unregistered.');
    }
}
