<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Not model-backed — see MeetingController::aiStatus. Lets the frontend
 * poll a single lightweight endpoint (FR-5.3) instead of re-fetching the
 * full transcript/summary just to check progress.
 */
class AiStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource['status'],
            'error_message' => $this->resource['error_message'],
        ];
    }
}
