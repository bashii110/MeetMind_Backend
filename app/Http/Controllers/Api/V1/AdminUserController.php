<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdminService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-16.1: admins view and disable/enable user accounts. */
class AdminUserController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AdminService $admin) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->admin->listUsers(
            $request->only(['search', 'is_disabled']),
            (int) $request->integer('per_page', 20),
        );

        return $this->success([
            'items' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function disable(User $user): JsonResponse
    {
        $user = $this->admin->disable($user);

        return $this->success(new UserResource($user), 'Account disabled.');
    }

    public function enable(User $user): JsonResponse
    {
        $user = $this->admin->enable($user);

        return $this->success(new UserResource($user), 'Account enabled.');
    }
}
