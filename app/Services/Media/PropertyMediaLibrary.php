<?php

namespace App\Services\Media;

use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PropertyMediaLibrary
{
    public const MAX_KILOBYTES = 5120;

    public function storePhoto(User $actor, Property $property, UploadedFile $file, string $directory = 'property-originals'): PropertyMedia
    {
        $this->assertImage($file);

        $original = $file->store($directory.'/'.$property->id, 'local');

        if ($original === false) {
            throw ValidationException::withMessages([
                'photo' => 'The photo could not be stored.',
            ]);
        }

        try {
            $derivative = $this->derivative($file, $property);
        } catch (ValidationException $exception) {
            Storage::disk('local')->delete($original);

            throw $exception;
        }

        $position = ((int) $property->media()->where('kind', 'photo')->max('position')) + 1;
        $cover = ! $property->media()->where('kind', 'photo')->where('is_cover', true)->exists();

        return $property->media()->create([
            'uploaded_by' => $actor->id,
            'kind' => 'photo',
            'disk' => 'local',
            'path' => $original,
            'public_disk' => 'local',
            'public_path' => $derivative,
            'original_name' => $file->getClientOriginalName(),
            'mime' => 'image/jpeg',
            'position' => $position,
            'is_cover' => $cover,
        ]);
    }

    public function storeVideo(User $actor, Property $property, string $url): PropertyMedia
    {
        $this->assertVideoUrl($url);

        $position = ((int) $property->media()->max('position')) + 1;

        return $property->media()->create([
            'uploaded_by' => $actor->id,
            'kind' => 'video',
            'disk' => 'local',
            'path' => 'video-links/'.$property->id,
            'original_name' => 'video-link',
            'mime' => null,
            'position' => $position,
            'is_cover' => false,
            'video_url' => $url,
        ]);
    }

    public function makeCover(Property $property, PropertyMedia $media): void
    {
        $this->assertOwnedPhoto($property, $media);
        $property->media()->where('kind', 'photo')->update(['is_cover' => false]);
        $media->update(['is_cover' => true]);
    }

    /**
     * @param  array<int|string, int|string>  $positions
     */
    public function reorder(Property $property, array $positions): void
    {
        $allowed = $property->media()->where('kind', 'photo')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $clean = [];

        foreach ($positions as $id => $position) {
            $id = (int) $id;

            if (! in_array($id, $allowed, true)) {
                throw ValidationException::withMessages([
                    'positions' => 'The photo order includes a file from another property.',
                ]);
            }

            $clean[$id] = (int) $position;
        }

        asort($clean);
        $index = 1;

        foreach (array_keys($clean) as $id) {
            $property->media()->whereKey($id)->update(['position' => $index]);
            $index++;
        }
    }

    public function delete(PropertyMedia $media): void
    {
        $propertyId = $media->property_id;
        $wasCover = (bool) $media->is_cover && $media->kind === 'photo';

        if ($media->path !== '' && ! str_starts_with($media->path, 'video-links/')) {
            Storage::disk($media->disk ?: 'local')->delete($media->path);
        }

        if (is_string($media->public_path) && $media->public_path !== '') {
            Storage::disk($media->public_disk ?: 'local')->delete($media->public_path);
        }

        $media->delete();

        if ($wasCover) {
            PropertyMedia::query()
                ->where('property_id', $propertyId)
                ->where('kind', 'photo')
                ->orderBy('position')
                ->limit(1)
                ->update(['is_cover' => true]);
        }
    }

    public static function isPublicDerivative(Property $property, PropertyMedia $media): bool
    {
        $path = $media->public_path;

        return $media->kind === 'photo'
            && $media->public_disk === 'local'
            && is_string($path)
            && ! str_contains($path, '..')
            && str_starts_with($path, 'property-derivatives/'.$property->id.'/');
    }

    public function assertImage(UploadedFile $file): void
    {
        $kilobytes = (int) ceil($file->getSize() / 1024);

        if ($kilobytes > self::MAX_KILOBYTES) {
            throw ValidationException::withMessages([
                'photo' => 'Photos must be 5 MB or smaller.',
            ]);
        }

        $mime = (string) $file->getMimeType();

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages([
                'photo' => 'Upload a JPEG, PNG, or WebP photo.',
            ]);
        }
    }

    public function assertVideoUrl(string $url): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = ['youtube.com', 'www.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com'];

        if (! str_starts_with($url, 'https://') || ! in_array($host, $allowed, true)) {
            throw ValidationException::withMessages([
                'video_url' => 'Use an https link from YouTube or Vimeo.',
            ]);
        }
    }

    private function assertOwnedPhoto(Property $property, PropertyMedia $media): void
    {
        if ($media->property_id !== $property->id || $media->kind !== 'photo') {
            throw ValidationException::withMessages([
                'photo' => 'That photo is not part of this property.',
            ]);
        }
    }

    private function derivative(UploadedFile $file, Property $property): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            throw ValidationException::withMessages([
                'photo' => 'Image processing is not available on this server.',
            ]);
        }

        $contents = file_get_contents($file->getRealPath());
        $image = is_string($contents) ? @imagecreatefromstring($contents) : false;

        if ($image === false) {
            throw ValidationException::withMessages([
                'photo' => 'That image could not be read.',
            ]);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $max = 1600;

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
        imagejpeg($image, null, 82);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        if (! is_string($jpeg) || $jpeg === '') {
            throw ValidationException::withMessages([
                'photo' => 'A public copy of that photo could not be made.',
            ]);
        }

        $path = 'property-derivatives/'.$property->id.'/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($path, $jpeg);

        return $path;
    }
}
