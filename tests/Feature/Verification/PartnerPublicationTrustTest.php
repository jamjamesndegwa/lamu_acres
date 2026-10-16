<?php

namespace Tests\Feature\Verification;

use App\Enums\OwnershipDocumentType;
use App\Enums\VerificationItemState;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\User;
use App\Services\Seller\SellerPortal;
use App\Services\Verification\VerificationOperations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class PartnerPublicationTrustTest extends FoundationTestCase
{
    public function test_publication_needs_marketing_authority_from_the_verified_owner(): void
    {
        [$property, $seller, $staff] = $this->eligibleProperty();
        $this->assertSame([], app(VerificationOperations::class)->blockers($property));
        $property->authorities()->delete();
        $property->unsetRelation('authorities');

        $this->actingAs($staff)->post(route('staff.properties.listing', $property))->assertSessionHasErrors('listing');
        $this->assertSame('review', $property->fresh()->status->value);
    }

    public function test_conflicting_required_ownership_evidence_blocks_but_optional_evidence_does_not(): void
    {
        Storage::fake('local');
        [$property, $seller, $staff] = $this->eligibleProperty();
        $portal = app(SellerPortal::class);
        $optional = $portal->storeDocument($property, $seller, UploadedFile::fake()->create('rates.pdf', 10, 'application/pdf'), OwnershipDocumentType::LandRates);
        $portal->reviewDocument($optional, $staff, VerificationItemState::Failed, 'Test-only optional rates evidence needs replacement.');
        $this->assertSame([], app(VerificationOperations::class)->blockers($property));
        $title = $portal->storeDocument($property, $seller, UploadedFile::fake()->create('title.pdf', 10, 'application/pdf'), OwnershipDocumentType::TitleDeed);
        $portal->reviewDocument($title, $staff, VerificationItemState::Failed, 'Test-only parcel ownership evidence conflicts.');

        $blockers = app(VerificationOperations::class)->blockers($property);

        $this->assertStringContainsString('conflicting or expired evidence', implode(' ', $blockers));
        $this->actingAs($staff)->post(route('staff.properties.listing', $property))->assertSessionHasErrors('listing');
        $replacement = $portal->storeDocument($property, $seller, UploadedFile::fake()->create('replacement-title.pdf', 10, 'application/pdf'), OwnershipDocumentType::TitleDeed);
        $this->assertStringContainsString('conflicting or expired evidence', implode(' ', app(VerificationOperations::class)->blockers($property)));
        $portal->reviewDocument($replacement, $staff, VerificationItemState::Verified, 'Test-only replacement resolves the ownership conflict.');
        $this->assertSame([], app(VerificationOperations::class)->blockers($property));

    }

    public function test_duplicate_parcels_need_a_reasoned_review_and_can_be_legitimately_shared(): void
    {
        [$property, $seller, $staff] = $this->eligibleProperty();
        Property::factory()->published()->create(['parcel_identifier' => $property->parcel_identifier]);
        $operations = app(VerificationOperations::class);
        $this->assertStringContainsString('Possible duplicate parcel', implode(' ', $operations->blockers($property)));
        $this->assertSame('review', $property->fresh()->status->value);

        $this->actingAs($staff)->post(route('staff.listings.duplicate-review', $property), [
            'outcome' => 'shared_marketing_authorized', 'reason' => 'Test-only review confirms shared authority for the same owner and parcel.',
        ])->assertSessionHasNoErrors();
        $this->assertSame([], $operations->blockers($property));
        Property::factory()->published()->create(['parcel_identifier' => $property->parcel_identifier]);
        $this->assertStringContainsString('Possible duplicate parcel', implode(' ', $operations->blockers($property)));
    }

    public function test_a_verified_owner_link_does_not_bypass_missing_seller_identity(): void
    {
        [$property, $seller, $staff] = $this->eligibleProperty();
        $seller->capabilityApplications()->delete();

        $this->actingAs($staff)->post(route('staff.properties.listing', $property))->assertSessionHasErrors('listing');

        $this->assertSame('review', $property->fresh()->status->value);
        $this->assertStringContainsString('seller identity application', implode(' ', app(VerificationOperations::class)->blockers($property)));
    }

    public function test_representative_capacity_must_match_the_owner_record(): void
    {
        [$property, $seller, $staff] = $this->eligibleProperty();
        $application = $seller->capabilityApplications()->firstOrFail();
        $profile = $application->verification;
        $profile['applicant_type'] = 'authorized_representative';
        $profile['evidence']['representative_authority'] = ['disk' => 'local', 'path' => 'partner-evidence/'.$seller->id.'/authority.pdf', 'mime' => 'application/pdf'];
        $application->forceFill(['verification' => $profile])->save();

        $blockers = app(VerificationOperations::class)->blockers($property);

        $this->assertStringContainsString('Correct the owner relationship', implode(' ', $blockers));
    }

    /** @return array{0: Property, 1: User, 2: User} */
    private function eligibleProperty(): array
    {
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties', 'staff.verification')->create();
        $county = County::query()->firstOrCreate(['slug' => 'lamu'], ['name' => 'Lamu', 'is_active' => true]);
        $locality = Locality::query()->firstOrCreate(['county_id' => $county->id, 'slug' => 'shela'], ['name' => 'Shela', 'kind' => 'place', 'is_active' => true]);
        $property = Property::factory()->listedIn($locality)->create([
            'submitted_by' => $seller->id, 'purpose' => 'sale', 'price_basis' => 'total',
            'title' => 'Trust fixture parcel', 'summary' => 'A test-only eligible parcel.',
            'acreage' => 3, 'price_amount' => 1000000, 'tenure' => 'freehold', 'ownership_structure' => 'individual',
            'status' => 'review', 'parcel_identifier' => 'TEST-PARCEL-'.fake()->unique()->numberBetween(1000, 999999),
        ]);
        $owner = $property->owners()->create(['user_id' => $seller->id, 'relationship' => 'owner', 'status' => 'pending']);
        app(SellerPortal::class)->verifyLink($owner, $staff, null, 'Test-only identity and parcel authority consistency review.', true);
        $property->authorities()->create(['seller_id' => $seller->id, 'scope' => 'sale', 'status' => 'active', 'terms' => 'Test-only current marketing instruction.']);
        $this->attachPublicPhoto($property, $seller);
        $this->clearCriticalVerification($property, $staff);

        return [$property->fresh(), $seller, $staff];
    }
}
