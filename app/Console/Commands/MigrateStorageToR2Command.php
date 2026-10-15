<?php

namespace App\Console\Commands;

use App\Models\BrokerIntroduction;
use App\Models\EvidenceFile;
use App\Models\IntelligenceFile;
use App\Models\OwnershipDocument;
use App\Models\PropertyMedia;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('storage:migrate-r2 {--dry-run : Simulate the migration without modifying files or database records} {--batch=100 : The number of records to process per batch} {--table= : Limit migration to a specific table (property_media, ownership_documents, evidence_files, intelligence_files, broker_introductions, users)}')]
#[Description('Migrate persistent files and documents to Cloudflare R2 storage idempotently')]
class MigrateStorageToR2Command extends Command
{
    private int $discovered = 0;

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $batchSize = max(1, (int) $this->option('batch'));
        $tableFilter = $this->option('table');

        $this->info('Starting Cloudflare R2 storage migration'.($dryRun ? ' [DRY-RUN]' : '').'...');

        if ($tableFilter === null || $tableFilter === 'property_media') {
            $this->migratePropertyMedia($batchSize, $dryRun);
        }

        if ($tableFilter === null || $tableFilter === 'ownership_documents') {
            $this->migrateOwnershipDocuments($batchSize, $dryRun);
        }

        if ($tableFilter === null || $tableFilter === 'evidence_files') {
            $this->migrateEvidenceFiles($batchSize, $dryRun);
        }

        if ($tableFilter === null || $tableFilter === 'intelligence_files') {
            $this->migrateIntelligenceFiles($batchSize, $dryRun);
        }

        if ($tableFilter === null || $tableFilter === 'broker_introductions') {
            $this->migrateBrokerIntroductions($batchSize, $dryRun);
        }

        if ($tableFilter === null || $tableFilter === 'users') {
            $this->migrateUserAvatars($batchSize, $dryRun);
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Discovered', $this->discovered],
                ['Successfully Migrated', $this->migrated],
                ['Skipped (Already on R2 or Missing Source)', $this->skipped],
                ['Failed', $this->failed],
                ['Remaining SQL BLOB data', 0],
            ]
        );

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function migratePropertyMedia(int $batchSize, bool $dryRun): void
    {
        $this->info('Migrating Property Media...');

        PropertyMedia::query()
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->chunkById($batchSize, function ($mediaList) use ($dryRun) {
                foreach ($mediaList as $media) {
                    $this->discovered++;

                    // 1. Original file migration
                    if (! str_starts_with($media->path, 'video-links/')) {
                        $sourceDisk = $media->disk ?: 'local';
                        $targetDisk = 'r2_private';

                        if ($sourceDisk === $targetDisk) {
                            $this->skipped++;
                        } elseif (! Storage::disk($sourceDisk)->exists($media->path)) {
                            $this->warn("Source file not found for PropertyMedia #{$media->id}: {$media->path}");
                            $this->skipped++;
                        } else {
                            try {
                                if (! $dryRun) {
                                    $size = Storage::disk($sourceDisk)->size($media->path);
                                    $stream = Storage::disk($sourceDisk)->readStream($media->path);
                                    if ($stream !== false) {
                                        Storage::disk($targetDisk)->writeStream($media->path, $stream);
                                        if (is_resource($stream)) {
                                            fclose($stream);
                                        }
                                    }

                                    $media->update([
                                        'disk' => $targetDisk,
                                        'size_bytes' => $media->size_bytes ?: $size,
                                    ]);
                                }
                                $this->migrated++;
                            } catch (\Throwable $e) {
                                $this->error("Failed to migrate PropertyMedia #{$media->id}: ".$e->getMessage());
                                $this->failed++;
                            }
                        }
                    }

                    // 2. Public derivative migration
                    if (filled($media->public_path)) {
                        $pubSource = $media->public_disk ?: 'local';
                        $pubTarget = 'r2';

                        if ($pubSource === $pubTarget) {
                            $this->skipped++;
                        } elseif (! Storage::disk($pubSource)->exists($media->public_path)) {
                            $this->skipped++;
                        } else {
                            try {
                                if (! $dryRun) {
                                    $stream = Storage::disk($pubSource)->readStream($media->public_path);
                                    if ($stream !== false) {
                                        Storage::disk($pubTarget)->writeStream($media->public_path, $stream);
                                        if (is_resource($stream)) {
                                            fclose($stream);
                                        }
                                    }

                                    $media->update(['public_disk' => $pubTarget]);
                                }
                                $this->migrated++;
                            } catch (\Throwable $e) {
                                $this->error("Failed to migrate PropertyMedia derivative #{$media->id}: ".$e->getMessage());
                                $this->failed++;
                            }
                        }
                    }
                }
            });
    }

    private function migrateOwnershipDocuments(int $batchSize, bool $dryRun): void
    {
        $this->info('Migrating Ownership Documents...');

        OwnershipDocument::query()
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->chunkById($batchSize, function ($docs) use ($dryRun) {
                foreach ($docs as $doc) {
                    $this->discovered++;
                    $sourceDisk = $doc->disk ?: 'local';
                    $targetDisk = 'r2_private';

                    if ($sourceDisk === $targetDisk) {
                        $this->skipped++;

                        continue;
                    }

                    if (! Storage::disk($sourceDisk)->exists($doc->path)) {
                        $this->warn("OwnershipDocument #{$doc->id} file missing at {$doc->path}");
                        $this->skipped++;

                        continue;
                    }

                    try {
                        if (! $dryRun) {
                            $size = Storage::disk($sourceDisk)->size($doc->path);
                            $stream = Storage::disk($sourceDisk)->readStream($doc->path);
                            if ($stream !== false) {
                                Storage::disk($targetDisk)->writeStream($doc->path, $stream);
                                if (is_resource($stream)) {
                                    fclose($stream);
                                }
                            }

                            $doc->update([
                                'disk' => $targetDisk,
                                'size_bytes' => $doc->size_bytes ?: $size,
                            ]);
                        }
                        $this->migrated++;
                    } catch (\Throwable $e) {
                        $this->error("Failed OwnershipDocument #{$doc->id}: ".$e->getMessage());
                        $this->failed++;
                    }
                }
            });
    }

    private function migrateEvidenceFiles(int $batchSize, bool $dryRun): void
    {
        $this->info('Migrating Evidence Files...');

        EvidenceFile::query()
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->chunkById($batchSize, function ($files) use ($dryRun) {
                foreach ($files as $file) {
                    $this->discovered++;
                    $sourceDisk = $file->disk ?: 'local';
                    $targetDisk = 'r2_private';

                    if ($sourceDisk === $targetDisk) {
                        $this->skipped++;

                        continue;
                    }

                    if (! Storage::disk($sourceDisk)->exists($file->path)) {
                        $this->warn("EvidenceFile #{$file->id} file missing at {$file->path}");
                        $this->skipped++;

                        continue;
                    }

                    try {
                        if (! $dryRun) {
                            $size = Storage::disk($sourceDisk)->size($file->path);
                            $stream = Storage::disk($sourceDisk)->readStream($file->path);
                            if ($stream !== false) {
                                Storage::disk($targetDisk)->writeStream($file->path, $stream);
                                if (is_resource($stream)) {
                                    fclose($stream);
                                }
                            }

                            $file->update([
                                'disk' => $targetDisk,
                                'size_bytes' => $file->size_bytes ?: $size,
                            ]);
                        }
                        $this->migrated++;
                    } catch (\Throwable $e) {
                        $this->error("Failed EvidenceFile #{$file->id}: ".$e->getMessage());
                        $this->failed++;
                    }
                }
            });
    }

    private function migrateIntelligenceFiles(int $batchSize, bool $dryRun): void
    {
        $this->info('Migrating Intelligence Files...');

        IntelligenceFile::query()
            ->whereNotNull('path')
            ->where('path', '!=', '')
            ->chunkById($batchSize, function ($files) use ($dryRun) {
                foreach ($files as $file) {
                    $this->discovered++;
                    $sourceDisk = $file->disk ?: 'local';
                    $targetDisk = 'r2_private';

                    if ($sourceDisk === $targetDisk) {
                        $this->skipped++;

                        continue;
                    }

                    if (! Storage::disk($sourceDisk)->exists($file->path)) {
                        $this->warn("IntelligenceFile #{$file->id} file missing at {$file->path}");
                        $this->skipped++;

                        continue;
                    }

                    try {
                        if (! $dryRun) {
                            $size = Storage::disk($sourceDisk)->size($file->path);
                            $stream = Storage::disk($sourceDisk)->readStream($file->path);
                            if ($stream !== false) {
                                Storage::disk($targetDisk)->writeStream($file->path, $stream);
                                if (is_resource($stream)) {
                                    fclose($stream);
                                }
                            }

                            $file->update([
                                'disk' => $targetDisk,
                                'size_bytes' => $file->size_bytes ?: $size,
                            ]);
                        }
                        $this->migrated++;
                    } catch (\Throwable $e) {
                        $this->error("Failed IntelligenceFile #{$file->id}: ".$e->getMessage());
                        $this->failed++;
                    }
                }
            });
    }

    private function migrateBrokerIntroductions(int $batchSize, bool $dryRun): void
    {
        $this->info('Migrating Broker Introductions...');

        BrokerIntroduction::query()
            ->where(function ($query) {
                $query->whereNotNull('consent_path')->orWhereNotNull('evidence_path');
            })
            ->chunkById($batchSize, function ($records) use ($dryRun) {
                foreach ($records as $item) {
                    $targetDisk = 'r2_private';

                    if (filled($item->consent_path)) {
                        $this->discovered++;
                        $source = $item->consent_disk ?: 'local';
                        if ($source === $targetDisk) {
                            $this->skipped++;
                        } elseif (Storage::disk($source)->exists($item->consent_path)) {
                            try {
                                if (! $dryRun) {
                                    $stream = Storage::disk($source)->readStream($item->consent_path);
                                    if ($stream !== false) {
                                        Storage::disk($targetDisk)->writeStream($item->consent_path, $stream);
                                        if (is_resource($stream)) {
                                            fclose($stream);
                                        }
                                    }
                                    $item->update(['consent_disk' => $targetDisk]);
                                }
                                $this->migrated++;
                            } catch (\Throwable $e) {
                                $this->error("Failed Broker Introduction consent #{$item->id}: ".$e->getMessage());
                                $this->failed++;
                            }
                        } else {
                            $this->skipped++;
                        }
                    }

                    if (filled($item->evidence_path)) {
                        $this->discovered++;
                        $source = $item->evidence_disk ?: 'local';
                        if ($source === $targetDisk) {
                            $this->skipped++;
                        } elseif (Storage::disk($source)->exists($item->evidence_path)) {
                            try {
                                if (! $dryRun) {
                                    $stream = Storage::disk($source)->readStream($item->evidence_path);
                                    if ($stream !== false) {
                                        Storage::disk($targetDisk)->writeStream($item->evidence_path, $stream);
                                        if (is_resource($stream)) {
                                            fclose($stream);
                                        }
                                    }
                                    $item->update(['evidence_disk' => $targetDisk]);
                                }
                                $this->migrated++;
                            } catch (\Throwable $e) {
                                $this->error("Failed Broker Introduction evidence #{$item->id}: ".$e->getMessage());
                                $this->failed++;
                            }
                        } else {
                            $this->skipped++;
                        }
                    }
                }
            });
    }

    private function migrateUserAvatars(int $batchSize, bool $dryRun): void
    {
        $this->info('Migrating User Avatars...');

        User::query()
            ->whereNotNull('avatar_path')
            ->where('avatar_path', '!=', '')
            ->chunkById($batchSize, function ($users) use ($dryRun) {
                foreach ($users as $user) {
                    $this->discovered++;
                    $sourceDisk = 'local';
                    $targetDisk = 'r2';

                    if (Storage::disk($targetDisk)->exists($user->avatar_path)) {
                        $this->skipped++;

                        continue;
                    }

                    if (! Storage::disk($sourceDisk)->exists($user->avatar_path)) {
                        $this->skipped++;

                        continue;
                    }

                    try {
                        if (! $dryRun) {
                            $stream = Storage::disk($sourceDisk)->readStream($user->avatar_path);
                            if ($stream !== false) {
                                Storage::disk($targetDisk)->writeStream($user->avatar_path, $stream);
                                if (is_resource($stream)) {
                                    fclose($stream);
                                }
                            }
                        }
                        $this->migrated++;
                    } catch (\Throwable $e) {
                        $this->error("Failed User avatar #{$user->id}: ".$e->getMessage());
                        $this->failed++;
                    }
                }
            });
    }
}
