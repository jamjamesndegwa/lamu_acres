<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToWriteFile;
use Throwable;

class R2Storage
{
    /**
     * When only public CLOUDFLARE_R2_* vars are set, copy them onto the r2_private disk
     * (empty CLOUDFLARE_R2_PRIVATE_* and stale config:cache values included).
     */
    public static function mergePrivateDiskFromPublic(): void
    {
        $publicBucket = config('filesystems.disks.r2.bucket');

        if (! filled($publicBucket)) {
            return;
        }

        $privatePrefix = 'filesystems.disks.r2_private';

        if (! filled(config("{$privatePrefix}.bucket"))) {
            Config::set("{$privatePrefix}.bucket", $publicBucket);
        }

        if (! filled(config("{$privatePrefix}.key"))) {
            Config::set("{$privatePrefix}.key", config('filesystems.disks.r2.key'));
        }

        if (! filled(config("{$privatePrefix}.secret"))) {
            Config::set("{$privatePrefix}.secret", config('filesystems.disks.r2.secret'));
        }

        if (! filled(config("{$privatePrefix}.endpoint"))) {
            Config::set("{$privatePrefix}.endpoint", config('filesystems.disks.r2.endpoint'));
        }
    }

    public static function cloudConfigured(): bool
    {
        return filled(config('filesystems.disks.r2.bucket'))
            && filled(config('filesystems.disks.r2.key'))
            && filled(config('filesystems.disks.r2.secret'))
            && filled(config('filesystems.disks.r2.endpoint'));
    }

    public static function privateUploadDisk(): string
    {
        if (! self::cloudConfigured()) {
            return 'local';
        }

        self::mergePrivateDiskFromPublic();

        return filled(config('filesystems.disks.r2_private.bucket')) ? 'r2_private' : 'local';
    }

    public static function publicUploadDisk(): string
    {
        return self::cloudConfigured() ? 'r2' : 'local';
    }

    /**
     * @throws ValidationException
     */
    public static function store(UploadedFile $file, string $directory, string $field, ?string $disk = null): string
    {
        $disk ??= self::privateUploadDisk();

        self::assertDiskReady($disk, $field);

        try {
            $path = $file->store($directory, $disk);
        } catch (Throwable $exception) {
            Log::warning('File upload to cloud storage failed.', [
                'disk' => $disk,
                'directory' => $directory,
                'field' => $field,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw self::validationFailure($exception, $field);
        }

        if ($path === false || ! is_string($path)) {
            throw ValidationException::withMessages([
                $field => 'The file could not be stored. Please try again.',
            ]);
        }

        return $path;
    }

    /**
     * @throws ValidationException
     */
    public static function assertDiskReady(string $disk, string $field): void
    {
        if ($disk === 'local') {
            return;
        }

        self::mergePrivateDiskFromPublic();

        $bucket = config("filesystems.disks.{$disk}.bucket");

        if (! filled($bucket)) {
            throw ValidationException::withMessages([
                $field => self::configurationMessage('The storage bucket is not configured.'),
            ]);
        }

        if (! filled(config("filesystems.disks.{$disk}.key"))
            || ! filled(config("filesystems.disks.{$disk}.secret"))
            || ! filled(config("filesystems.disks.{$disk}.endpoint"))) {
            throw ValidationException::withMessages([
                $field => self::configurationMessage('Cloudflare R2 credentials or endpoint are missing.'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public static function validationFailureForField(Throwable $exception, string $field): ValidationException
    {
        return self::validationFailure($exception, $field);
    }

    /**
     * @throws ValidationException
     */
    private static function validationFailure(Throwable $exception, string $field): ValidationException
    {
        $detail = $exception->getMessage();

        if ($exception instanceof UnableToWriteFile && $exception->getPrevious() !== null) {
            $detail = $exception->getPrevious()->getMessage();
        }

        $userMessage = self::configurationMessage(
            'We could not save your file to Cloudflare R2. Check bucket name and API token permissions, then try again.',
        );

        if (config('app.debug')) {
            $userMessage .= ' Debug: '.$detail;
        }

        return ValidationException::withMessages([$field => $userMessage]);
    }

    private static function configurationMessage(string $base): string
    {
        return $base;
    }
}
