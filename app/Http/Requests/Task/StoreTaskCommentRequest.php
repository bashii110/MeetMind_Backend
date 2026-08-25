<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:2000'],
            // IDs the frontend's @mention autocomplete resolved while
            // composing the comment (see TaskService::addComment) — each
            // must be a real user and, more specifically, a member of
            // this task's workspace (checked in withValidator below,
            // since that needs the route-bound Task).
            'mentioned_user_ids' => ['sometimes', 'array'],
            'mentioned_user_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $mentioned = $this->input('mentioned_user_ids', []);

            if (empty($mentioned)) {
                return;
            }

            /** @var \App\Models\Task $task */
            $task = $this->route('task');
            $memberIds = $task->workspace->members()->pluck('users.id')->all();

            foreach ($mentioned as $userId) {
                if (! in_array((int) $userId, $memberIds, true)) {
                    $validator->errors()->add('mentioned_user_ids', 'One or more mentioned users are not members of this workspace.');
                    break;
                }
            }
        });
    }
}
