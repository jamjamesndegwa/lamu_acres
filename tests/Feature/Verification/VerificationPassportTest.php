<?php

namespace Tests\Feature\Verification;

use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Enums\VerificationLevel;
use App\Enums\VerificationOverride;
use App\Models\Property;
use App\Models\User;
use App\Models\VerificationItem;
use App\Notifications\ReverificationDueNotification;
use App\Services\Verification\VerificationDesk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Foundation\FoundationTestCase;

class VerificationPassportTest extends FoundationTestCase
{
    public function test_uploading_evidence_does_not_verify_a_checklist_item(): void
    {
        [$staff, $property, $item] = $this->passport();

        $this->actingAs($staff)
            ->post(route('staff.verification.evidence', $item), [
                'file' => UploadedFile::fake()->create('title.pdf', 20, 'application/pdf'),
                'sensitivity' => 'title',
            ])
            ->assertRedirect();

        $this->assertSame(VerificationItemState::Received, $item->refresh()->state);
        $this->assertSame(VerificationLevel::Advertised, $property->passport()->first()->level);

        $this->actingAs($staff)
            ->put(route('staff.verification.review', $item), [
                'state' => VerificationItemState::Verified->value,
                'evidence_note' => 'Original title compared with the registry extract.',
            ])
            ->assertRedirect();

        $this->assertSame(VerificationItemState::Verified, $item->refresh()->state);
        $this->assertNotNull($item->reviewer_id);
        $this->assertSame(VerificationLevel::Advertised, $property->passport()->first()->refresh()->level);
    }

    public function test_failed_or_expired_critical_checks_are_not_treated_as_verified(): void
    {
        [$staff, $property] = $this->passport();
        $desk = app(VerificationDesk::class);

        foreach ([VerificationCategory::TitleOwnership, VerificationCategory::OfficialSearch, VerificationCategory::SellerAuthority, VerificationCategory::Encumbrances] as $category) {
            $desk->review($property->verificationItems()->where('category', $category)->first(), $staff, [
                'state' => VerificationItemState::Verified->value,
                'evidence_note' => 'Checked against the file.',
            ]);
        }

        $this->assertSame(VerificationLevel::OwnershipChecked, $property->passport()->first()->refresh()->level);

        $title = $property->verificationItems()->where('category', VerificationCategory::TitleOwnership)->first();
        $desk->review($title, $staff, [
            'state' => VerificationItemState::Failed->value,
            'evidence_note' => 'The title name does not match the seller.',
        ]);

        $this->assertSame(VerificationItemState::Failed, $title->refresh()->state);
        $this->assertSame(VerificationLevel::Advertised, $property->passport()->first()->refresh()->level);

        $search = $property->verificationItems()->where('category', VerificationCategory::OfficialSearch)->first();
        $search->forceFill([
            'state' => VerificationItemState::Verified,
            'expires_at' => now()->subDay(),
        ])->save();
        $desk->recalculate($property);

        $this->assertSame(VerificationItemState::Expired, $search->refresh()->state);

        $property->load('verificationItems');
        $summary = $desk->publicSummary($property);
        $searchLine = collect($summary['items'])->firstWhere('category', 'Official search');

        $this->assertSame('Outstanding', $searchLine['outcome']);
        $this->assertSame('Level 0 — Advertised', $summary['level']);

        $this->actingAs($staff)
            ->put(route('staff.verification.override', $property), [
                'override' => VerificationOverride::Suspended->value,
                'critical_exception' => 'Boundary dispute remains open.',
            ])
            ->assertRedirect();

        $this->assertSame(VerificationLevel::Advertised, $property->passport()->first()->refresh()->level);
        $property->unsetRelation('passport');
        $property->unsetRelation('verificationItems');
        $this->assertSame('Verification suspended', $desk->publicSummary($property->load(['passport', 'verificationItems']))['level']);
    }

    public function test_reverification_reminders_go_to_the_verification_desk(): void
    {
        Notification::fake();

        [$staff, $property] = $this->passport();
        $item = $property->verificationItems()->first();
        $item->forceFill([
            'state' => VerificationItemState::Verified,
            'review_due_at' => now()->addDays(3),
        ])->save();

        $this->assertSame(1, app(VerificationDesk::class)->remind());
        Notification::assertSentTo($staff, ReverificationDueNotification::class);
        $this->assertNotNull($item->refresh()->reminded_at);
    }

    /**
     * @return array{0: User, 1: Property, 2?: VerificationItem}
     */
    private function passport(): array
    {
        $seller = User::factory()->withRoles('seller')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.verification')->create();

        $this->actingAs($seller)->post(route('seller.properties.store'), ['title' => 'Mokowe plot', 'purpose' => 'sale', 'relationship' => 'owner']);
        $property = Property::query()->where('title', 'Mokowe plot')->first();

        return [$staff, $property, $property->verificationItems()->where('category', VerificationCategory::TitleOwnership)->first()];
    }
}
