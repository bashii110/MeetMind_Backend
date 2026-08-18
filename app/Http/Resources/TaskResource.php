<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'meeting_id' => $this->meeting_id,
            'meeting_title' => $this->whenLoaded('meeting', fn () => $this->meeting?->title),
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority?->value,
            'status' => $this->status?->value,
            'deadline' => $this->deadline?->toIso8601String(),
            'progress' => $this->progress,
            'is_overdue' => $this->isOverdue(),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'assignee' => $this->assignee ? new UserResource($this->assignee) : null,
            'comment_count' => $this->whenCounted('comments'),
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'attachments' => TaskAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
