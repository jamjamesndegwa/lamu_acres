<?php

namespace App\Services\Broker;

use App\Enums\BrokerIntroductionStatus;
use App\Models\BrokerIntroduction;
use App\Models\BrokerIntroductionAudit;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ReferralDecidedNotification;
use App\Notifications\ReferralSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
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
        $email = Str::lower(trim((string) $data['buyer_email']));
        $buyer = User::query()->where('email', $email)->first();
        $flags = $this->conflicts->detect($broker, $propertyIds, $email, $data['buyer_phone'] ?? null);
        $properties = Property::query()->publiclyListed()->whereIn('id', $propertyIds)->get();

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
            'consent_path' => $consent->store('broker-referrals/consent', 'local'),
            'evidence_path' => $evidence?->store('broker-referrals/evidence', 'local'),
            'source' => $data['source'],
            'commission_terms' => $data['commission_terms'],
            'notes' => $data['notes'] ?? null,
            'conflicts' => $flags,
        ]);

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
        $from = $introduction->status;

        $introduction->update([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $staff->id,
        ]);

        $this->audit($introduction, $staff, 'reviewed', $from, $status, $note);

        $introduction->broker?->notify(new ReferralDecidedNotification($introduction));

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
