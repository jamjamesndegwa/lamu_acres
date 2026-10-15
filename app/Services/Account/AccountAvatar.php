<?php

namespace App\Services\Account;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountAvatar
{
    public static function disk(): string
    {
        return config('filesystems.disks.r2.bucket') ? 'r2' : 'local';
    }

    public function store(User $user, UploadedFile $file): void
    {
        $jpeg = $this->jpeg($file);
        $disk = self::disk();
        $path = 'avatars/'.$user->id.'-'.Str::random(8).'.jpg';

        $oldPath = $user->avatar_path;
        Storage::disk($disk)->put($path, $jpeg);

        try {
            $user->forceFill(['avatar_path' => $path])->touch();
            if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $path) {
                Storage::disk($disk)->delete($oldPath);
                if ($disk !== 'local') {
                    Storage::disk('local')->delete($oldPath);
                }
            }
        } catch (\Throwable $throwable) {
            Storage::disk($disk)->delete($path);

            throw $throwable;
        }
    }

    public function delete(User $user): void
    {
        $disk = self::disk();

        if (is_string($user->avatar_path) && $user->avatar_path !== '') {
            Storage::disk($disk)->delete($user->avatar_path);
            if ($disk !== 'local') {
                Storage::disk('local')->delete($user->avatar_path);
            }
        }

        $user->forceFill(['avatar_path' => null])->save();
    }

    private function jpeg(UploadedFile $file): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            throw ValidationException::withMessages([
                'avatar' => 'Image processing is not available on this server.',
            ]);
        }

        $contents = file_get_contents($file->getRealPath());
        $image = is_string($contents) ? @imagecreatefromstring($contents) : false;

        if ($image === false) {
            throw ValidationException::withMessages([
                'avatar' => 'That image could not be read.',
            ]);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $max = 512;

        if ($width > $max || $height > $max) {
            $ratio = min($max / $width, $max / $height);
            $targetWidth = max(1, (int) round($width * $ratio));
            $targetHeight = max(1, (int) round($height * $ratio));
            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        if (! is_string($jpeg) || $jpeg === '') {
            throw ValidationException::withMessages([
                'avatar' => 'That photo could not be saved.',
            ]);
        }

        return $jpeg;
    }
}
