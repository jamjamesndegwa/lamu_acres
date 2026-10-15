<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

trait StoresFileUrls
{
    public static function bootStoresFileUrls(): void
    {
        static::saving(function (Model $model): void {
            $columns = match ($model->getTable()) {
                'users' => [['avatar_path', null, 'avatar_url']],
                'property_media' => [['path', 'disk', 'url'], ['public_path', 'public_disk', 'public_url']],
                'broker_introductions' => [['consent_path', 'consent_disk', 'consent_url'], ['evidence_path', 'evidence_disk', 'evidence_url']],
                default => [['path', 'disk', 'url']],
            };

            foreach ($columns as [$pathColumn, $diskColumn, $urlColumn]) {
                if (! $model->isDirty($pathColumn) && ($diskColumn === null || ! $model->isDirty($diskColumn))) {
                    continue;
                }

                $path = $model->getAttribute($pathColumn);
                $disk = $diskColumn === null
                    ? (config('filesystems.disks.r2.bucket') ? 'r2' : 'local')
                    : ($model->getAttribute($diskColumn) ?: 'local');

                $model->setAttribute($urlColumn, filled($path) && $disk !== 'local' && ! str_starts_with($path, 'video-links/')
                    ? (filled(config("filesystems.disks.{$disk}.url"))
                        ? rtrim(config("filesystems.disks.{$disk}.url"), '/').'/'.ltrim($path, '/')
                        : Storage::disk($disk)->url($path))
                    : null);
            }
        });
    }
}
