<?php

namespace App\Services\Verification;

use App\Enums\EvidenceSensitivity;
use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Enums\VerificationLevel;
use App\Enums\VerificationOverride;
use App\Models\EvidenceFile;
use App\Models\EvidenceGrant;
use App\Models\Property;
use App\Models\User;
use App\Models\VerificationItem;
use App\Models\VerificationPassport;
use App\Notifications\ReverificationDueNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class VerificationDesk
{
    public function seed(Property $property): VerificationPassport
    {
        $passport = $property->passport()->firstOrCreate([], [
            'level' => VerificationLevel::Advertised,
            'override' => VerificationOverride::None,
        ]);

        foreach (VerificationCategory::cases() as $category) {
            $property->verificationItems()->firstOrCreate(
                ['category' => $category],
                ['state' => VerificationItemState::NotSupplied],
            );
        }

        return $this->recalculate($property);
    }

    public function storeEvidence(VerificationItem $item, User $uploader, UploadedFile $file, EvidenceSensitivity $sensitivity): EvidenceFile
    {
        $disk = config('filesystems.disks.r2_private.bucket') || config('filesystems.disks.r2.bucket') ? 'r2_private' : 'local';
        $path = $file->store('evidence/'.$item->property_id, $disk);

        if ($path === false) {
            throw ValidationException::withMessages([
                'file' => 'The evidence file could not be stored.',
            ]);
        }

        try {
            $evidence = $item->files()->create([
                'property_id' => $item->property_id,
                'uploaded_by' => $uploader->id,
                'sensitivity' => $sensitivity,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'disk' => $disk,
                'mime' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        } catch (\Throwable $throwable) {
            Storage::disk($disk)->delete($path);

            throw $throwable;
        }

        if ($item->state === VerificationItemState::NotSupplied) {
            $item->state = VerificationItemState::Received;
            $item->evidence_note = 'File received. Not yet reviewed.';
            $item->save();
        }

        $this->recalculate($item->property);

        return $evidence;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function review(VerificationItem $item, User $reviewer, array $data): VerificationItem
    {
        $state = VerificationItemState::from((string) $data['state']);

        if ($state === VerificationItemState::Verified && $item->files()->doesntExist() && blank($data['evidence_note'] ?? null)) {
            throw ValidationException::withMessages([
                'state' => 'A check is not verified just because a file was uploaded. Record the review evidence.',
            ]);
        }

        if (in_array($state, [VerificationItemState::Failed, VerificationItemState::Expired], true) && $item->category->critical()) {
            $state = $state;
        }

        $item->fill([
            'state' => $state,
            'reviewer_id' => $reviewer->id,
            'evidence_note' => $data['evidence_note'] ?? $item->evidence_note,
            'checked_at' => now(),
            'expires_at' => array_key_exists('expires_at', $data) ? $data['expires_at'] : $item->expires_at,
            'review_due_at' => array_key_exists('review_due_at', $data) ? $data['review_due_at'] : $item->review_due_at,
            'notes' => $data['notes'] ?? $item->notes,
        ]);
        $item->save();
        $item->property->verificationReviews()->create([
            'verification_item_id' => $item->id,
            'actor_id' => $reviewer->id,
            'decision' => $state->value,
            'reason' => (string) ($data['evidence_note'] ?? $data['notes'] ?? 'Review recorded.'),
        ]);
        $this->recalculate($item->property);

        return $item;
    }

    public function setOverride(VerificationPassport $passport, VerificationOverride $override, ?string $exception, User $reviewer): VerificationPassport
    {
        $passport->override = $override;
        $passport->critical_exception = $exception;
        $passport->suspended_at = $override === VerificationOverride::Suspended ? now() : null;
        $passport->save();

        $passport->property->verificationItems()->where('category', VerificationCategory::TitleOwnership)->update([
            'reviewer_id' => $reviewer->id,
        ]);

        return $this->recalculate($passport->property);
    }

    public function recalculate(Property $property): VerificationPassport
    {
        $passport = $property->passport()->firstOrCreate([], [
            'level' => VerificationLevel::Advertised,
            'override' => VerificationOverride::None,
        ]);

        $this->expireDueItems($property);
        $items = $property->verificationItems()->get()->keyBy(fn (VerificationItem $item) => $item->category->value);

        if ($items->isEmpty()) {
            $passport->level = VerificationLevel::Advertised;
            $passport->save();

            return $passport;
        }

        $criticalFailed = $items->contains(fn (VerificationItem $item): bool => $item->category->critical()
            && in_array($item->state, [VerificationItemState::Failed, VerificationItemState::Expired], true));

        $received = $this->critical($items)->every(fn (VerificationItem $item): bool => in_array($item->state, [
            VerificationItemState::Received,
            VerificationItemState::UnderReview,
            VerificationItemState::Verified,
            VerificationItemState::NotApplicable,
        ], true)) && $this->critical($items)->contains(fn (VerificationItem $item): bool => $item->state !== VerificationItemState::NotApplicable);

        $checked = $this->critical($items)->every(fn (VerificationItem $item): bool => $item->state === VerificationItemState::Verified);
        $enhanced = $items->count() === count(VerificationCategory::cases())
            && $items->every(fn (VerificationItem $item): bool => in_array($item->state, [VerificationItemState::Verified, VerificationItemState::NotApplicable], true))
            && $checked;

        $level = VerificationLevel::Advertised;
        if ($received && ! $criticalFailed && blank($passport->critical_exception)) {
            $level = VerificationLevel::DocumentsReceived;
        }
        if ($checked && ! $criticalFailed && blank($passport->critical_exception)) {
            $level = VerificationLevel::OwnershipChecked;
        }
        if ($enhanced && ! $criticalFailed && blank($passport->critical_exception)) {
            $level = VerificationLevel::Enhanced;
        }

        if (in_array($passport->override, [VerificationOverride::Suspended, VerificationOverride::Expired], true)) {
            $level = VerificationLevel::Advertised;
        }

        $passport->level = $level;
        $passport->save();
        $property->forceFill([
            'verification_level' => $level->value,
            'last_reviewed_at' => now(),
        ])->save();

        return $passport;
    }

    public function grant(EvidenceFile $file, User $recipient, User $staff, \DateTimeInterface $expiresAt): EvidenceGrant
    {
        if ($expiresAt <= now()) {
            throw ValidationException::withMessages(['expires_at' => 'The access window must be in the future.']);
        }

        return $file->grants()->create([
            'user_id' => $recipient->id,
            'approved_by' => $staff->id,
            'expires_at' => $expiresAt,
        ]);
    }

    public function download(EvidenceFile $file, User $user, bool $inline = false): Response
    {
        $allowed = $user->hasPermission('staff.verification') || $this->activeGrant($file, $user) !== null;

        $file->accessLogs()->create([
            'user_id' => $user->id,
            'outcome' => $allowed ? 'allowed' : 'refused',
            'accessed_at' => now(),
        ]);

        abort_unless($allowed, 403);

        if ($inline) {
            $mime = Storage::disk($file->disk)->mimeType($file->path);
            abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true), 415);

            return Storage::disk($file->disk)->response($file->path, $file->original_name, [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        if ($file->disk !== 'local') {
            return redirect()->away(Storage::disk($file->disk)->temporaryUrl($file->path, now()->addMinutes(15)));
        }

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function signedLink(EvidenceFile $file, EvidenceGrant $grant): string
    {
        return URL::temporarySignedRoute('evidence.download', $grant->expires_at, [
            'evidenceFile' => $file->id,
        ]);
    }

    /**
     * @return array{level: string, override: string, items: array<int, array{category: string, outcome: string}>}
     */
    public function publicSummary(Property $property): array
    {
        $passport = $property->passport;
        $override = $passport?->override ?? VerificationOverride::None;
        $level = $passport?->level ?? VerificationLevel::Advertised;

        if (in_array($override, [VerificationOverride::Suspended, VerificationOverride::Expired], true) || filled($passport?->critical_exception)) {
            $level = VerificationLevel::Advertised;
        }

        $items = $property->verificationItems->map(function (VerificationItem $item): array {
            $outcome = match (true) {
                $item->state === VerificationItemState::Verified && ($item->expires_at === null || $item->expires_at->isFuture()) => 'Verified',
                $item->state === VerificationItemState::NotApplicable => 'Not applicable',
                in_array($item->state, [VerificationItemState::Received, VerificationItemState::UnderReview], true) => $item->state->label(),
                default => 'Outstanding',
            };

            return [
                'category' => $item->category->label(),
                'outcome' => $outcome,
            ];
        })->values()->all();

        return [
            'level' => $override === VerificationOverride::Suspended ? 'Verification suspended' : $level->label(),
            'override' => $override === VerificationOverride::None ? null : $override->label(),
            'items' => $items,
        ];
    }

    public function remind(): int
    {
        $due = VerificationItem::query()
            ->where('state', VerificationItemState::Verified)
            ->whereNotNull('review_due_at')
            ->where('review_due_at', '<=', now()->addDays(14))
            ->whereNull('reminded_at')
            ->with('property')
            ->get();

        $staff = User::permission('staff.verification')->get();

        foreach ($due as $item) {
            Notification::send($staff, new ReverificationDueNotification($item));
            $item->forceFill(['reminded_at' => now()])->save();
        }

        return $due->count();
    }

    private function expireDueItems(Property $property): void
    {
        $property->verificationItems()
            ->where('state', VerificationItemState::Verified)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'state' => VerificationItemState::Expired->value,
            ]);
    }

    /**
     * @param  Collection<string, VerificationItem>  $items
     * @return Collection<string, VerificationItem>
     */
    private function critical($items)
    {
        return $items->filter(fn (VerificationItem $item): bool => $item->category->critical());
    }

    private function activeGrant(EvidenceFile $file, User $user): ?EvidenceGrant
    {
        return $file->grants()
            ->where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();
    }
}
