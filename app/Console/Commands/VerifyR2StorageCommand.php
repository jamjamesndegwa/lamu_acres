<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use Throwable;

#[Signature('storage:verify-r2')]
#[Description('Verify Flysystem S3/R2 is installed and can read/write (run after deploy before testing land uploads)')]
class VerifyR2StorageCommand extends Command
{
    public function handle(): int
    {
        if (! class_exists(PortableVisibilityConverter::class)) {
            $this->error('Missing league/flysystem-aws-s3-v3. Run: composer install --no-dev');

            return self::FAILURE;
        }

        $this->info('Flysystem AWS S3 adapter: OK');

        $disks = ['r2_private', 'r2'];
        $failed = false;

        foreach ($disks as $disk) {
            $config = config("filesystems.disks.{$disk}");

            if (! is_array($config) || ($config['bucket'] ?? '') === '') {
                $this->warn("Disk [{$disk}]: no bucket configured — uploads may use local storage instead.");

                continue;
            }

            if (($config['key'] ?? '') === '' || ($config['secret'] ?? '') === '' || ($config['endpoint'] ?? '') === '') {
                $this->error("Disk [{$disk}]: missing key, secret, or endpoint in .env");

                $failed = true;

                continue;
            }

            $path = 'health-checks/r2-verify-'.uniqid('', true).'.txt';

            try {
                Storage::disk($disk)->put($path, 'lamu-acres-r2-verify');
                $contents = Storage::disk($disk)->get($path);
                Storage::disk($disk)->delete($path);

                if ($contents !== 'lamu-acres-r2-verify') {
                    throw new \RuntimeException('Read back unexpected contents.');
                }

                $this->info("Disk [{$disk}] bucket [{$config['bucket']}]: read/write OK");
            } catch (Throwable $exception) {
                $this->error("Disk [{$disk}]: {$exception->getMessage()}");
                $failed = true;
            }
        }

        if ($failed) {
            $this->newLine();
            $this->comment('Fix CLOUDFLARE_R2_* in .env and token permissions in Cloudflare, then run again.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('R2 is ready for staff land registration and photo uploads.');

        return self::SUCCESS;
    }
}
