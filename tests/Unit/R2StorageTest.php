<?php

namespace Tests\Unit;

use App\Support\R2Storage;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class R2StorageTest extends TestCase
{
    public function test_merge_private_disk_uses_public_bucket_when_private_bucket_is_empty(): void
    {
        Config::set('filesystems.disks.r2.bucket', 'lamuacres');
        Config::set('filesystems.disks.r2.key', 'public-key');
        Config::set('filesystems.disks.r2.secret', 'public-secret');
        Config::set('filesystems.disks.r2.endpoint', 'https://example.r2.cloudflarestorage.com');
        Config::set('filesystems.disks.r2_private.bucket', '');
        Config::set('filesystems.disks.r2_private.key', '');
        Config::set('filesystems.disks.r2_private.secret', '');
        Config::set('filesystems.disks.r2_private.endpoint', '');

        R2Storage::mergePrivateDiskFromPublic();

        $this->assertSame('lamuacres', config('filesystems.disks.r2_private.bucket'));
        $this->assertSame('public-key', config('filesystems.disks.r2_private.key'));
        $this->assertSame('public-secret', config('filesystems.disks.r2_private.secret'));
        $this->assertSame('https://example.r2.cloudflarestorage.com', config('filesystems.disks.r2_private.endpoint'));
    }

    public function test_private_upload_disk_falls_back_to_r2_private_when_only_public_is_configured(): void
    {
        Config::set('filesystems.disks.r2.bucket', 'lamuacres');
        Config::set('filesystems.disks.r2.key', 'public-key');
        Config::set('filesystems.disks.r2.secret', 'public-secret');
        Config::set('filesystems.disks.r2.endpoint', 'https://example.r2.cloudflarestorage.com');
        Config::set('filesystems.disks.r2_private.bucket', '');

        Config::set('filesystems.disks.r2_private.key', 'stale-private-key');

        R2Storage::mergePrivateDiskFromPublic();

        $this->assertSame('public-key', config('filesystems.disks.r2_private.key'));
        $this->assertSame('r2_private', R2Storage::privateUploadDisk());
    }
}
