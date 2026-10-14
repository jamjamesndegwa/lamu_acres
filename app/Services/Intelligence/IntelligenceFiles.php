<?php

namespace App\Services\Intelligence;

use App\Models\DevelopmentProject;
use App\Models\ImageryScene;
use App\Models\IntelligenceFile;
use App\Models\PlanningRecord;
use App\Models\ResearchReport;
use App\Models\User;
use App\Models\VerifiedSale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IntelligenceFiles
{
    public function store(Model $subject, User $actor, UploadedFile $upload, string $kind): IntelligenceFile
    {
        $path = $upload->store('intelligence/'.$subject->getTable().'/'.$subject->getKey(), 'local');

        return $subject->files()->create([
            'kind' => $kind,
            'disk' => 'local',
            'path' => $path,
            'original_name' => basename(str_replace('\\', '/', $upload->getClientOriginalName())) ?: 'file',
            'mime' => $upload->getClientMimeType(),
            'uploaded_by' => $actor->id,
        ]);
    }

    public function response(User|null $user, IntelligenceFile $file): StreamedResponse
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

        return Storage::disk($file->disk)->response($file->path, $file->original_name);
    }
}
