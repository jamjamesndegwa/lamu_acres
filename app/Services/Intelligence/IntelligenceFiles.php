<?php

namespace App\Services\Intelligence;

use App\Models\DevelopmentProject;
use App\Models\ImageryScene;
use App\Models\IntelligenceFile;
use App\Models\PlanningRecord;
use App\Models\ResearchReport;
use App\Models\User;
use App\Models\VerifiedSale;
use App\Support\R2Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class IntelligenceFiles
{
    public function store(Model $subject, User $actor, UploadedFile $upload, string $kind): IntelligenceFile
    {
        $disk = R2Storage::privateUploadDisk();
        $path = R2Storage::store($upload, 'intelligence/'.$subject->getTable().'/'.$subject->getKey(), 'file', $disk);

        try {
            return $subject->files()->create([
                'kind' => $kind,
                'disk' => $disk,
                'path' => $path,
                'original_name' => basename(str_replace('\\', '/', $upload->getClientOriginalName())) ?: 'file',
                'mime' => $upload->getClientMimeType(),
                'size_bytes' => $upload->getSize(),
                'uploaded_by' => $actor->id,
            ]);
        } catch (\Throwable $throwable) {
            Storage::disk($disk)->delete($path);

            throw $throwable;
        }
    }

    public function response(?User $user, IntelligenceFile $file): Response
    {
        $file->loadMissing('subject');
        $subject = $file->subject;
        abort_unless($subject instanceof Model, 404);
        abort_unless(is_string($file->path) && ! str_contains($file->path, '..') && str_starts_with($file->path, 'intelligence/'), 404);

        $staff = $user?->hasPermission('staff.intelligence') || $user?->hasPermission('intelligence.publish');

        if (! $staff) {
            abort_unless(method_exists($subject, 'isReleased') && $subject->isReleased(), 404);
            abort_unless($user?->hasActiveInvestorPass(), 403);

            if ($subject instanceof ImageryScene) {
                abort_unless($subject->redistribution_allowed, 403);
            }
        }

        abort_unless($subject instanceof DevelopmentProject
            || $subject instanceof ImageryScene
            || $subject instanceof PlanningRecord
            || $subject instanceof VerifiedSale
            || $subject instanceof ResearchReport, 404);

        if ($file->disk !== 'local') {
            return redirect()->away(Storage::disk($file->disk)->temporaryUrl($file->path, now()->addMinutes(15)));
        }

        return Storage::disk($file->disk)->response($file->path, $file->original_name);
    }
}
