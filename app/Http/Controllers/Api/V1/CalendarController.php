<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\CalendarRequest;
use App\Services\CalendarService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * FR-8.1/8.2. Deliberately view-agnostic: the frontend asks for whatever
 * date range the currently-visible month/week/day grid needs rather than
 * the backend knowing about "views" at all.
 */
class CalendarController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CalendarService $calendar) {}

    public function index(CalendarRequest $request): JsonResponse
    {
        $data = $this->calendar->forUser(
            $request->user(),
            $request->date('start'),
            $request->date('end'),
        );

        return $this->success($data);
    }
}
