<?php

namespace Tests\Feature\Seller;

use App\Enums\ListingPurpose;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\OwnershipDocumentType;
use App\Enums\OwnershipStructure;
use App\Enums\PriceBasis;
use App\Enums\PropertyStatus;
use App\Enums\VerificationItemState;
use App\Models\County;
use App\Models\Locality;
use App\Models\OwnershipDocument;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\User;
use App\Services\Seller\SellerPortal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class SellerDocumentTest extends FoundationTestCase
{
    public function test_seller_can_upload_typed_ownership_document_and_state_is_received_not_verified(): void
    {
        Storage::fake('local');
        [$seller, $property] = $this->createSellerDraft();

        $response = $this->actingAs($seller)->post(route('seller.properties.documents.store', $property), [
            'document_type' => OwnershipDocumentType::NationalId->value,
            'document' => UploadedFile::fake()->create('id-card.pdf', 500, 'application/pdf'),
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $document = OwnershipDocument::query()->where('property_id', $property->id)->first();
        $this->assertNotNull($document);
        $this->assertSame(OwnershipDocumentType::NationalId, $document->document_type);
        $this->assertSame(VerificationItemState::Received, $document->state);
        $this->assertSame($seller->id, $document->user_id);
        $this->assertTrue($document->hasFile());

        // Uploading does NOT verify the property or the owner link
        $owner = $property->owners()->where('user_id', $seller->id)->first();
        $this->assertSame(OwnerLinkStatus::Pending, $owner->status);

        $idItem = $property->verificationItems()->where('category', OwnershipDocumentType::NationalId->category())->first();
        if ($idItem !== null) {
            $this->assertNotSame(VerificationItemState::Verified, $idItem->state);
            $this->assertSame(VerificationItemState::Received, $idItem->state);
        }
    }

    public function test_invalid_document_type_or_oversized_file_fails_validation(): void
    {
        Storage::fake('local');
        [$seller, $property] = $this->createSellerDraft();

        // 1. Oversized file (> 10MB / 10240KB)
        $this->actingAs($seller)->post(route('seller.properties.documents.store', $property), [
            'document_type' => OwnershipDocumentType::TitleDeed->value,
            'document' => UploadedFile::fake()->create('huge-deed.pdf', 12000, 'application/pdf'),
        ])->assertSessionHasErrors('document');

        // 2. Disallowed MIME type (e.g. text/plain or executable)
        $this->actingAs($seller)->post(route('seller.properties.documents.store', $property), [
            'document_type' => OwnershipDocumentType::TitleDeed->value,
            'document' => UploadedFile::fake()->create('script.sh', 10, 'application/x-sh'),
        ])->assertSessionHasErrors('document');

        // 3. Invalid document_type enum value
        $this->actingAs($seller)->post(route('seller.properties.documents.store', $property), [
            'document_type' => 'unknown_certificate',
            'document' => UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf'),
        ])->assertSessionHasErrors('document_type');
    }

    public function test_privacy_and_access_control_for_ownership_documents(): void
    {
        Storage::fake('local');
        [$seller, $property] = $this->createSellerDraft();
        $otherSeller = User::factory()->withRoles('seller')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.verification')->create();

        $this->actingAs($seller)->post(route('seller.properties.documents.store', $property), [
            'document_type' => OwnershipDocumentType::TitleDeed->value,
            'document' => UploadedFile::fake()->create('title-deed.pdf', 200, 'application/pdf'),
        ]);

        $document = OwnershipDocument::query()->where('property_id', $property->id)->firstOrFail();

        // Unrelated seller cannot download the document
        $this->actingAs($otherSeller)
            ->get(route('seller.documents.download', $document))
            ->assertForbidden();

        // Submitter can download their own document
        $this->actingAs($seller)
            ->get(route('seller.documents.download', $document))
            ->assertOk();

        // Verification staff can download the document and an audit record is created
        $beforeAudits = PropertyAudit::query()->where('property_id', $property->id)->count();
        $this->actingAs($staff)
            ->get(route('staff.ownership-documents.download', $document))
            ->assertOk();

        $this->assertGreaterThan($beforeAudits, PropertyAudit::query()->where('property_id', $property->id)->count());
        $audit = PropertyAudit::query()->where('property_id', $property->id)->where('action', 'ownership_document_opened')->first();
        $this->assertNotNull($audit);
        $this->assertSame($staff->id, $audit->actor_id);
    }

    public function test_staff_can_review_document_and_record_not_applicable(): void
    {
        Storage::fake('local');
        [$seller, $property] = $this->createSellerDraft();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.verification')->create();

        $this->actingAs($seller)->post(route('seller.properties.documents.store', $property), [
            'document_type' => OwnershipDocumentType::TitleDeed->value,
            'document' => UploadedFile::fake()->create('title.pdf', 200, 'application/pdf'),
        ]);
        $document = OwnershipDocument::query()->where('property_id', $property->id)->firstOrFail();

        // Short reason fails validation (< 8 characters)
        $this->actingAs($staff)
            ->put(route('staff.ownership-documents.review', $document), [
                'state' => VerificationItemState::Verified->value,
                'reason' => 'Ok',
            ])
            ->assertSessionHasErrors('reason');

        // Valid staff review
        $this->actingAs($staff)
            ->put(route('staff.ownership-documents.review', $document), [
                'state' => VerificationItemState::Verified->value,
                'reason' => 'Verified against official land registry search record.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $document->refresh();
        $this->assertSame(VerificationItemState::Verified, $document->state);
        $this->assertSame($staff->id, $document->reviewed_by);
        $this->assertNotNull($document->reviewed_at);

        // Staff marks a document type not applicable
        $this->actingAs($staff)
            ->post(route('staff.ownership-documents.not-applicable', $property), [
                'document_type' => OwnershipDocumentType::SpousalConsent->value,
                'reason' => 'Seller is unmarried as per sworn statutory declaration.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $naDoc = OwnershipDocument::query()
            ->where('property_id', $property->id)
            ->where('document_type', OwnershipDocumentType::SpousalConsent)
            ->first();

        $this->assertNotNull($naDoc);
        $this->assertSame(VerificationItemState::NotApplicable, $naDoc->state);
        $this->assertNull($naDoc->path);
        $this->assertSame($staff->id, $naDoc->reviewed_by);
    }

    public function test_conditional_rules_and_staff_items_do_not_block_submission(): void
    {
        Storage::fake('local');
        [$seller, $property] = $this->createSellerDraft();

        // Upload only required documents: national_id and title_deed
        foreach ([OwnershipDocumentType::NationalId->value => 'id.pdf', OwnershipDocumentType::TitleDeed->value => 'deed.pdf'] as $type => $name) {
            $this->actingAs($seller)->post(route('seller.properties.documents.store', $property), [
                'document_type' => $type,
                'document' => UploadedFile::fake()->create($name, 50, 'application/pdf'),
            ]);
        }

        // Add a photo so submission requirement for photos is satisfied
        $this->actingAs($seller)->post(route('seller.properties.photos.store', $property), [
            'photo' => UploadedFile::fake()->image('plot.jpg'),
        ]);

        $portal = app(SellerPortal::class);
        $requirements = $portal->documentRequirements($property);

        // Verify that conditional items (CoOwnerAuthorization, PowerOfAttorney, CompanyResolution) are not required for individual direct owner
        $reqTypes = collect($requirements)->pluck('type')->all();
        $this->assertNotContains(OwnershipDocumentType::CoOwnerAuthorization->value, $reqTypes);
        $this->assertNotContains(OwnershipDocumentType::PowerOfAttorney->value, $reqTypes);
        $this->assertNotContains(OwnershipDocumentType::CompanyResolution->value, $reqTypes);

        // Spousal consent is staff_to_determine and official search is staff_obtains
        $spousal = collect($requirements)->firstWhere('type', OwnershipDocumentType::SpousalConsent->value);
        $this->assertSame(SellerPortal::STAFF_TO_DETERMINE, $spousal['requirement']);

        $search = collect($requirements)->firstWhere('type', OwnershipDocumentType::OfficialSearch->value);
        $this->assertSame(SellerPortal::STAFF_OBTAINS, $search['requirement']);

        // Gaps does not require spousal consent or official search
        $gaps = $portal->submissionGaps($property);
        $this->assertArrayNotHasKey('documents.'.OwnershipDocumentType::SpousalConsent->value, $gaps);
        $this->assertArrayNotHasKey('documents.'.OwnershipDocumentType::OfficialSearch->value, $gaps);

        // Submitting for review succeeds
        $this->actingAs($seller)
            ->post(route('seller.properties.submission', $property))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(PropertyStatus::Review, $property->refresh()->status);
    }

    /**
     * @return array{0: User, 1: Property}
     */
    private function createSellerDraft(): array
    {
        $seller = User::factory()->withRoles('seller')->create();
        $county = County::query()->firstOrCreate(['slug' => 'lamu'], ['name' => 'Lamu', 'is_active' => true]);
        $locality = Locality::query()->firstOrCreate(
            ['county_id' => $county->id, 'slug' => 'shela'],
            ['name' => 'Shela', 'kind' => 'place', 'is_active' => true]
        );

        $property = Property::factory()->listedIn($locality)->create([
            'title' => 'Shela Beachfront Holding',
            'summary' => 'A prime parcel ready for sale.',
            'description' => 'Detailed description of the parcel.',
            'purpose' => ListingPurpose::Sale,
            'land_use' => \App\Enums\LandUse::Agricultural,
            'tenure' => \App\Enums\LandTenure::Freehold,
            'price_basis' => PriceBasis::Total,
            'price_amount' => 5_000_000,
            'acreage' => 3.0,
            'parcel_identifier' => 'LAMU/SHELA/DOC-'.fake()->unique()->numberBetween(100, 999),
            'parcel_reference_type' => 'title_number',
            'ownership_structure' => OwnershipStructure::Individual,
            'status' => PropertyStatus::Draft,
            'submitted_by' => $seller->id,
        ]);

        $property->owners()->create([
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Pending,
        ]);

        return [$seller, $property];
    }
}
