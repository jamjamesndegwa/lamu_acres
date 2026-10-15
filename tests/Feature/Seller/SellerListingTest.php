<?php

namespace Tests\Feature\Seller;

use App\Enums\LandTenure;
use App\Enums\LandUse;
use App\Enums\ListingPurpose;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\OwnershipStructure;
use App\Enums\ParcelReferenceType;
use App\Enums\PriceBasis;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\User;
use App\Notifications\PropertySubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class SellerListingTest extends FoundationTestCase
{
    public function test_seller_can_create_sale_draft_with_price_basis_and_derivation(): void
    {
        $seller = User::factory()->withRoles('seller')->create();
        $locality = $this->createLocality();

        $response = $this->actingAs($seller)->post(route('seller.properties.store'), [
            'purpose' => ListingPurpose::Sale->value,
            'relationship' => OwnerRelationship::Owner->value,
            'title' => 'Shela Beach Plot',
            'summary' => 'Prime sand dunes holding in Shela.',
            'description' => 'Level ground, clear access path, ready for development.',
            'property_type' => PropertyType::Residential->value,
            'land_use' => LandUse::Residential->value,
            'county_id' => $locality->county_id,
            'locality_id' => $locality->id,
            'acreage' => 4.0,
            'offered_acreage' => 4.0,
            'tenure' => LandTenure::Freehold->value,
            'ownership_structure' => OwnershipStructure::Individual->value,
            'parcel_identifier' => 'LAMU/SHELA/400',
            'parcel_reference_type' => ParcelReferenceType::TitleNumber->value,
            'sale' => [
                'price_basis' => PriceBasis::PerAcre->value,
                'amount' => 500_000,
                'negotiable' => true,
            ],
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $property = Property::query()->where('title', 'Shela Beach Plot')->first();
        $this->assertNotNull($property);
        $this->assertSame(ListingPurpose::Sale, $property->purpose);
        $this->assertSame(PropertyStatus::Draft, $property->status);
        $this->assertSame(PropertyAvailability::Unavailable, $property->availability);
        $this->assertSame($seller->id, $property->submitted_by);
        $this->assertSame(PriceBasis::PerAcre, $property->price_basis);
        $this->assertSame(500_000, $property->price_rate);
        $this->assertSame(2_000_000, $property->price_amount); // 4 acres * 500k
        $this->assertTrue($property->price_negotiable);

        $owner = $property->owners()->where('user_id', $seller->id)->first();
        $this->assertNotNull($owner);
        $this->assertSame(OwnerRelationship::Owner, $owner->relationship);
        $this->assertSame(OwnerLinkStatus::Pending, $owner->status);
    }

    public function test_seller_can_create_lease_draft_with_monthly_rent_and_duration(): void
    {
        $seller = User::factory()->withRoles('seller')->create();
        $locality = $this->createLocality();

        $response = $this->actingAs($seller)->post(route('seller.properties.store'), [
            'purpose' => ListingPurpose::Lease->value,
            'relationship' => OwnerRelationship::Owner->value,
            'title' => 'Manda Island Farm Lease',
            'summary' => 'Agricultural lease on fertile soil.',
            'county_id' => $locality->county_id,
            'locality_id' => $locality->id,
            'acreage' => 10.0,
            'tenure' => LandTenure::Freehold->value,
            'ownership_structure' => OwnershipStructure::Individual->value,
            'parcel_identifier' => 'LAMU/MANDA/120',
            'parcel_reference_type' => ParcelReferenceType::TitleNumber->value,
            'lease' => [
                'price_basis' => PriceBasis::ParcelMonth->value,
                'amount' => 60_000,
                'min_months' => 24,
                'max_months' => 120,
            ],
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $property = Property::query()->where('title', 'Manda Island Farm Lease')->first();
        $this->assertNotNull($property);
        $this->assertSame(ListingPurpose::Lease, $property->purpose);
        $this->assertSame(PriceBasis::ParcelMonth, $property->price_basis);
        $this->assertSame(60_000, $property->price_amount);
        $this->assertSame(24, $property->lease_min_months);
        $this->assertSame(120, $property->lease_max_months);
    }

    public function test_seller_listing_validation_and_acreage_limits(): void
    {
        $seller = User::factory()->withRoles('seller')->create();
        $locality = $this->createLocality();

        // 1. Missing base required fields
        $this->actingAs($seller)->post(route('seller.properties.store'), [])
            ->assertSessionHasErrors(['title', 'purpose', 'relationship']);

        // 2. Locality mismatched with county
        $otherCounty = County::query()->create(['name' => 'Tana River', 'slug' => 'tana-river', 'is_active' => true]);
        $this->actingAs($seller)->post(route('seller.properties.store'), [
            'title' => 'Invalid County Locality',
            'purpose' => 'sale',
            'relationship' => 'owner',
            'county_id' => $otherCounty->id,
            'locality_id' => $locality->id,
        ])->assertSessionHasErrors('locality_id');

        // 3. Offered acreage exceeds parcel acreage
        $this->actingAs($seller)->post(route('seller.properties.store'), [
            'title' => 'Excessive Acreage',
            'purpose' => 'sale',
            'relationship' => 'owner',
            'acreage' => 5.0,
            'offered_acreage' => 6.0,
            'county_id' => $locality->county_id,
            'locality_id' => $locality->id,
        ])->assertSessionHasErrors('offered_acreage');
    }

    public function test_seller_can_edit_draft_but_not_submitted_property(): void
    {
        $seller = User::factory()->withRoles('seller')->create();
        $locality = $this->createLocality();

        $property = Property::factory()->listedIn($locality)->create([
            'title' => 'Original Draft Title',
            'purpose' => ListingPurpose::Sale,
            'price_basis' => PriceBasis::Total,
            'price_amount' => 3_000_000,
            'acreage' => 2.0,
            'status' => PropertyStatus::Draft,
            'submitted_by' => $seller->id,
        ]);
        $property->owners()->create([
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Pending,
        ]);

        // Edit screen accessible
        $this->actingAs($seller)
            ->get(route('seller.properties.edit', $property))
            ->assertOk()
            ->assertSee('Original Draft Title');

        // Update works
        $this->actingAs($seller)
            ->put(route('seller.properties.update', $property), [
                'title' => 'Updated Draft Title',
                'purpose' => ListingPurpose::Sale->value,
                'relationship' => OwnerRelationship::Owner->value,
                'county_id' => $locality->county_id,
                'locality_id' => $locality->id,
                'acreage' => 2.0,
                'sale' => ['price_basis' => 'total', 'amount' => 3_500_000],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Updated Draft Title', $property->refresh()->title);
        $this->assertSame(3_500_000, $property->price_amount);

        // Transition property to Review
        $property->update(['status' => PropertyStatus::Review]);

        // Now editing is forbidden
        $this->actingAs($seller)
            ->get(route('seller.properties.edit', $property))
            ->assertForbidden();

        $this->actingAs($seller)
            ->put(route('seller.properties.update', $property), [
                'title' => 'Should Not Update',
                'purpose' => 'sale',
                'relationship' => 'owner',
            ])
            ->assertForbidden();
    }

    public function test_seller_uploads_multiple_photos_in_one_request(): void
    {
        config([
            'filesystems.disks.r2.bucket' => 'public-photos',
            'filesystems.disks.r2.url' => 'https://cdn.example.com',
            'filesystems.disks.r2_private.bucket' => 'private-photos',
        ]);
        Storage::fake('r2');
        Storage::fake('r2_private');
        $seller = User::factory()->withRoles('seller')->create();
        $property = Property::factory()->listedIn($this->createLocality())->create([
            'submitted_by' => $seller->id,
            'status' => PropertyStatus::Draft,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.properties.photos.store', $property), [
                'photos' => [
                    UploadedFile::fake()->image('first.jpg'),
                    UploadedFile::fake()->image('second.png'),
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $photos = $property->media()->orderBy('position')->get();
        $this->assertSame(['first.jpg', 'second.png'], $photos->pluck('original_name')->all());
        $this->assertSame([1, 2], $photos->pluck('position')->all());
        $this->assertSame([true, false], $photos->pluck('is_cover')->all());
        foreach ($photos as $photo) {
            $this->assertSame('r2_private', $photo->disk);
            $this->assertSame('r2', $photo->public_disk);
            $this->assertStringStartsWith('https://cdn.example.com/property-derivatives/', $photo->public_url);
            Storage::disk('r2_private')->assertExists($photo->path);
            Storage::disk('r2')->assertExists($photo->public_path);
        }
    }

    public function test_invalid_photo_rejects_the_entire_selection(): void
    {
        Storage::fake('local');
        $seller = User::factory()->withRoles('seller')->create();
        $property = Property::factory()->listedIn($this->createLocality())->create([
            'submitted_by' => $seller->id,
            'status' => PropertyStatus::Draft,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.properties.photos.store', $property), [
                'photos' => [
                    UploadedFile::fake()->image('valid.jpg'),
                    UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
                ],
            ])
            ->assertSessionHasErrors('photos.1');

        $this->assertSame(0, $property->media()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_batch_cannot_exceed_the_remaining_listing_photo_slots(): void
    {
        Storage::fake('local');
        $seller = User::factory()->withRoles('seller')->create();
        $property = Property::factory()->listedIn($this->createLocality())->create([
            'submitted_by' => $seller->id,
            'status' => PropertyStatus::Draft,
        ]);
        for ($index = 0; $index < 11; $index++) {
            $this->attachPublicPhoto($property, $seller);
        }

        $this->actingAs($seller)
            ->post(route('seller.properties.photos.store', $property), [
                'photos' => [
                    UploadedFile::fake()->image('first.jpg'),
                    UploadedFile::fake()->image('second.jpg'),
                ],
            ])
            ->assertSessionHasErrors(['photos' => 'A listing can hold up to 12 photographs.']);

        $this->assertSame(11, $property->media()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_unreadable_photo_rolls_back_files_already_uploaded_in_the_batch(): void
    {
        Storage::fake('local');
        $seller = User::factory()->withRoles('seller')->create();
        $property = Property::factory()->listedIn($this->createLocality())->create([
            'submitted_by' => $seller->id,
            'status' => PropertyStatus::Draft,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.properties.photos.store', $property), [
                'photos' => [
                    UploadedFile::fake()->image('valid.jpg'),
                    UploadedFile::fake()->create('broken.jpg', 10, 'image/jpeg'),
                ],
            ])
            ->assertSessionHasErrors(['photo' => 'That image could not be read.']);

        $this->assertSame(0, $property->media()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_seller_can_manage_listing_photos(): void
    {
        Storage::fake('local');
        $seller = User::factory()->withRoles('seller')->create();
        $locality = $this->createLocality();

        $property = Property::factory()->listedIn($locality)->create([
            'title' => 'Photo Managed Property',
            'status' => PropertyStatus::Draft,
            'submitted_by' => $seller->id,
        ]);
        $property->owners()->create([
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Pending,
        ]);

        // Upload photo 1
        $this->actingAs($seller)
            ->post(route('seller.properties.photos.store', $property), [
                'photo' => UploadedFile::fake()->image('photo1.jpg'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        // Upload photo 2
        $this->actingAs($seller)
            ->post(route('seller.properties.photos.store', $property), [
                'photo' => UploadedFile::fake()->image('photo2.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $photos = $property->media()->where('kind', 'photo')->orderBy('id')->get();
        $this->assertCount(2, $photos);
        $first = $photos[0];
        $second = $photos[1];

        // Make second photo cover
        $this->actingAs($seller)
            ->put(route('seller.properties.photos.cover', [$property, $second]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertTrue((bool) $second->refresh()->is_cover);
        $this->assertFalse((bool) $first->refresh()->is_cover);

        // Reorder photos
        $this->actingAs($seller)
            ->put(route('seller.properties.photos.order', $property), [
                'positions' => [$second->id => 1, $first->id => 2],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(1, $second->refresh()->position);
        $this->assertSame(2, $first->refresh()->position);

        // Delete photo
        $this->actingAs($seller)
            ->delete(route('seller.properties.photos.destroy', [$property, $first]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertCount(1, $property->media()->where('kind', 'photo')->get());
    }

    public function test_submitting_draft_for_review_checks_gaps_and_transitions_to_review(): void
    {
        Storage::fake('local');
        Notification::fake();

        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $seller = User::factory()->withRoles('seller')->create();
        $locality = $this->createLocality();

        $property = Property::factory()->listedIn($locality)->create([
            'title' => 'Shela Dunes Draft',
            'summary' => 'Brief summary.',
            'description' => 'Detailed description.',
            'purpose' => ListingPurpose::Sale,
            'property_type' => PropertyType::Agricultural,
            'land_use' => LandUse::Agricultural,
            'tenure' => LandTenure::Freehold,
            'ownership_structure' => OwnershipStructure::Individual,
            'price_basis' => PriceBasis::Total,
            'price_amount' => 4_000_000,
            'acreage' => 5.0,
            'parcel_identifier' => 'LAMU/SHELA/SUB-1',
            'parcel_reference_type' => ParcelReferenceType::TitleNumber,
            'status' => PropertyStatus::Draft,
            'submitted_by' => $seller->id,
        ]);
        $property->owners()->create([
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Pending,
        ]);

        // Attempt submission without photos and documents -> should fail
        $this->actingAs($seller)
            ->post(route('seller.properties.submission', $property))
            ->assertSessionHasErrors(['photos', 'documents.national_id', 'documents.title_deed']);

        $this->assertSame(PropertyStatus::Draft, $property->refresh()->status);

        // Add photo
        $this->actingAs($seller)
            ->post(route('seller.properties.photos.store', $property), [
                'photo' => UploadedFile::fake()->image('land.jpg'),
            ]);

        // Add documents
        foreach (['national_id' => 'id.pdf', 'title_deed' => 'deed.pdf'] as $type => $filename) {
            $this->actingAs($seller)
                ->post(route('seller.properties.documents.store', $property), [
                    'document_type' => $type,
                    'document' => UploadedFile::fake()->create($filename, 50, 'application/pdf'),
                ]);
        }

        // Now submission succeeds
        $this->actingAs($seller)
            ->post(route('seller.properties.submission', $property))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $property->refresh();
        $this->assertSame(PropertyStatus::Review, $property->status);
        $this->assertNotNull($property->submitted_at);

        Notification::assertSentTo(
            User::permission('staff.properties')->get(),
            PropertySubmittedNotification::class
        );
    }

    public function test_isolation_between_sellers_and_index_grouping(): void
    {
        $sellerA = User::factory()->withRoles('seller')->create();
        $sellerB = User::factory()->withRoles('seller')->create();
        $locality = $this->createLocality();

        $propertyA = Property::factory()->listedIn($locality)->create([
            'title' => 'Seller A Private Holding',
            'status' => PropertyStatus::Draft,
            'submitted_by' => $sellerA->id,
        ]);
        $propertyA->owners()->create([
            'user_id' => $sellerA->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Pending,
        ]);

        // Seller A sees it in their index under Drafts
        $this->actingAs($sellerA)
            ->get(route('seller.properties.index'))
            ->assertOk()
            ->assertSeeInOrder(['Drafts', 'Seller A Private Holding']);

        // Seller B cannot see it in index or show
        $this->actingAs($sellerB)
            ->get(route('seller.properties.index'))
            ->assertOk()
            ->assertDontSee('Seller A Private Holding');

        $this->actingAs($sellerB)
            ->get(route('seller.properties.show', $propertyA))
            ->assertForbidden();

        // When submitted for review, Seller A sees it under 'Awaiting ownership verification'
        $propertyA->update(['status' => PropertyStatus::Review]);

        $this->actingAs($sellerA)
            ->get(route('seller.properties.index'))
            ->assertOk()
            ->assertSeeInOrder(['Awaiting ownership verification', 'Seller A Private Holding']);
    }

    private function createLocality(): Locality
    {
        $county = County::query()->firstOrCreate(['slug' => 'lamu'], ['name' => 'Lamu', 'is_active' => true]);

        return Locality::query()->firstOrCreate(
            ['county_id' => $county->id, 'slug' => 'shela'],
            ['name' => 'Shela', 'kind' => 'place', 'is_active' => true]
        );
    }
}
