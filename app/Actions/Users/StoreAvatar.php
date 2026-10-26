<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\ImageResizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A profile photo from any camera or gallery: cropped to a 400 px square
 * JPEG on the public disk, replacing the previous one.
 */
class StoreAvatar
{
    public const SIZE = 400;

    public function handle(User $user, UploadedFile $photo): string
    {
        $disk = Storage::disk('public');
        $path = 'avatars/'.Str::lower(Str::ulid()).'.jpg';
        $disk->put($path, ImageResizer::square($photo->getRealPath(), self::SIZE));

        $this->remove($user);
        $user->forceFill(['avatar' => $path])->save();

        return $path;
    }

    public function remove(User $user): void
    {
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->forceFill(['avatar' => null])->save();
        }
    }
}
