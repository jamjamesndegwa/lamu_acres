<?php

namespace App\Services\Broker;

use App\Enums\BrokerIntroductionStatus;
use App\Enums\MessageEvent;
use App\Models\BrokerIntroduction;
use App\Models\BrokerIntroductionAudit;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ReferralDecidedNotification;
use App\Notifications\ReferralSubmittedNotification;
use App\Services\Access\CapabilityReviewer;
use App\Services\Communications\MessageDesk;
use App\Support\R2Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReferralRecorder
{
    public function __construct(private ReferralConflictDetector $conflicts) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $propertyIds
     */
    public function submit(User $broker, array $data, array $propertyIds, UploadedFile $consent, ?UploadedFile $evidence = null): BrokerIntroduction
    {
        app(CapabilityReviewer::class)->assertBrokerCanAct($broker);
        $email = Str::lower(trim((string) $data['buyer_email']));
        $buyer = User::query()->where('email', $email)->first();
        $flags = $this->conflicts->detect($broker, $propertyIds, $email, $data['buyer_phone'] ?? null);
        $properties = Property::query()->publiclyListed()->whereIn('id', $propertyIds)->get();

        $disk = R2Storage::privateUploadDisk();
        $consentPath = R2Storage::store($consent, 'broker-referrals/consent', 'consent', $disk);
        $evidencePath = $evidence instanceof UploadedFile
            ? R2Storage::store($evidence, 'broker-referrals/evidence', 'evidence', $disk)
            : null;

        try {
            $introduction = BrokerIntroduction::query()->create([
                'reference' => $this->reference(),
                'broker_id' => $broker->id,
                'buyer_id' => $buyer?->id,
                'buyer_name' => trim((string) $data['buyer_name']),
                'buyer_email' => $email,
                'buyer_phone' => $data['buyer_phone'] ?? null,
                'property_id' => $properties->first()?->id,
                'status' => BrokerIntroductionStatus::Submitted,
                'introduced_at' => $data['introduced_at'],
                'submitted_at' => now(),
                'consent_note' => $data['consent_note'],
                'consent_path' => $consentPath,
                'consent_disk' => $disk,
                'evidence_path' => $evidencePath,
                'evidence_disk' => $evidence !== null ? $disk : 'local',
                'source' => $data['source'],
                'commission_terms' => $data['commission_terms'],
                'notes' => $data['notes'] ?? null,
                'conflicts' => $flags,
            ]);
        } catch (\Throwable $throwable) {
            if ($consentPath) {
                Storage::disk($disk)->delete($consentPath);
            }
            if ($evidencePath) {
                Storage::disk($disk)->delete($evidencePath);
            }

            throw $throwable;
        }

        $introduction->properties()->sync($properties->modelKeys());

        $this->audit($introduction, $broker, 'submitted', null, BrokerIntroductionStatus::Submitted, $flags === []
            ? null
            : 'Conflicts were recorded for staff review. Nothing was rejected automatically.');

        Notification::send(
            User::permission('staff.brokerage')->get(),
            new ReferralSubmittedNotification($introduction),
        );

        return $introduction;
    }

    public function decide(User $staff, BrokerIntroduction $introduction, BrokerIntroductionStatus $status, ?string $note): BrokerIntroduction
    {
        $from = $introduction->fresh()->status;
        if ($from === $status) {
            return $introduction;
        }

        $introduction->update([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $staff->id,
        ]);

        $this->audit($introduction, $staff, 'reviewed', $from, $status, $note);

        if ($status !== BrokerIntroductionStatus::Accepted) {
            $introduction->broker?->notify(new ReferralDecidedNotification($introduction));
        }

        if ($status === BrokerIntroductionStatus::Accepted && $introduction->broker !== null) {
            app(MessageDesk::class)->send($introduction->broker, MessageEvent::ReferralAccepted, [
                'name' => $introduction->broker->name,
                'title' => 'Referral accepted',
                'summary' => 'Your referral was accepted.',
                'url' => route('broker.dashboard'),
            ], 'referral:'.$introduction->id, new ReferralDecidedNotification($introduction));
        }

        return $introduction;
    }

    private function audit(
        BrokerIntroduction $introduction,
        User $actor,
        string $action,
        ?BrokerIntroductionStatus $from,
        BrokerIntroductionStatus $to,
        ?string $note,
    ): void {
        BrokerIntroductionAudit::query()->create([
            'broker_introduction_id' => $introduction->id,
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
        ]);
    }

    private function reference(): string
    {
        do {
            $reference = 'RF-'.strtoupper(Str::random(8));
        } while (BrokerIntroduction::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
