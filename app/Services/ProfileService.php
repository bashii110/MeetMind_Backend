<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    public function update(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $attributes = collect($data)
            ->only(['name', 'bio', 'company', 'position', 'timezone', 'skills'])
            ->toArray();

        if ($avatar) {
            $attributes['avatar'] = $this->storeAvatar($user, $avatar);
        }

        return $this->users->update($user, $attributes);
    }

    private function storeAvatar(User $user, UploadedFile $avatar): string
    {
        if ($user->avatar) {
            Storage::disk('public')->delete($this->pathFromUrl($user->avatar));
        }

        $path = $avatar->store('avatars', 'public');

        return Storage::disk('public')->url($path);
    }

    private function pathFromUrl(string $url): string
    {
        return ltrim(parse_url($url, PHP_URL_PATH) ?? '', '/storage/');
    }
}
