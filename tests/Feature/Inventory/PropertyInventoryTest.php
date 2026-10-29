<?php

namespace Tests\Feature\Inventory;

use App\Enums\AuthorityStatus;
use App\Enums\LandTenure;
use App\Enums\LandUse;
use App\Enums\ListingPurpose;
use App\Enums\MarketingRole;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\OwnershipStructure;
use App\Enums\ParcelReferenceType;
use App\Enums\PriceBasis;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\User;
use App\Services\Media\PropertyMediaLibrary;
use App\Services\Seller\SellerPortal;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Foundation\FoundationTestCase;

class PropertyInventoryTest extends FoundationTestCase
{
    public function test_staff_can_register_a_complete_listing_and_it_is_published_immediately(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $locality = $this->locality();

        $payload = $this->payload($locality, $seller);

        $this->actingAs($buyer)->post(route('staff.properties.store'), $payload)->assertForbidden();

        $response = $this->actingAs($staff)->post(route('staff.properties.store'), $payload);

        $property = Property::query()->where('title', 'Mokowe holding')->firstOrFail();
        $response->assertRedirect(route('staff.properties.show', $property));

        $this->assertSame(100_000, $property->pricePerAcre());
        $this->assertSame(PropertyStatus::Published, $property->status);
        $this->assertSame(MarketingRole::Owner, $property->marketing_role);
        $this->assertNotNull($property->reference);
        $this->assertNotNull($property->published_at);
        $this->assertTrue($property->isStaffRepresentedListing());
        $this->assertTrue(PropertyAudit::query()->where('property_id', $property->id)->where('action', 'registered by staff')->exists());
        $this->assertTrue(PropertyAudit::query()->where('property_id', $property->id)->where('action', 'approved')->exists());
        $this->assertTrue($property->owners()->where('user_id', $seller->id)->where('status', OwnerLinkStatus::Verified)->exists());
        $this->assertTrue($property->authorities()->where('seller_id', $seller->id)->where('status', AuthorityStatus::Active)->exists());
        $this->assertGreaterThan(0, $property->media()->where('kind', 'photo')->count());

        $this->get(route('land.show', [$locality->county, $locality, $property]))
            ->assertOk()
            ->assertDontSee('9.8765432');
    }

    public function test_selling_a_portion_does_not_sell_the_parent_parcel(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $locality = $this->locality();

        $parent = Property::factory()->published()->listedIn($locality)->create([
            'title' => 'Parent parcel',
            'acreage' => 10,
            'price_amount' => 1_000_000,
            'description' => 'Parent holding.',
        ]);

        $portion = Property::factory()->published()->listedIn($locality)->create([
            'title' => 'North portion',
            'parent_id' => $parent->id,
            'acreage' => 4,
            'price_amount' => 400_000,
            'portion_label' => 'North',
            'description' => 'North portion holding.',
        ]);
        $this->assertSame(100_000, $portion->pricePerAcre());
        $this->actingAs($staff)->post(route('staff.properties.sell-portion', $portion))->assertSessionHasErrors('portion');

        $corrector = User::factory()->withRoles('staff')->withPermissions('staff.properties', 'staff.inventory.correct')->create();
        $this->actingAs($corrector)->post(route('staff.properties.sell-portion', $portion), [
            'correction_reason' => 'Legacy portion sale imported before the sales desk went live.',
        ])->assertRedirect();

        $this->assertSame(PropertyStatus::Sold, $portion->fresh()->status);
        $this->assertSame(PropertyStatus::Published, $parent->fresh()->status);
        $this->assertEquals(6.0, $parent->fresh()->remainingAcreage());
        $this->assertTrue($parent->fresh()->isPubliclyListed());
    }

    public function test_a_locality_must_belong_to_the_selected_county(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $locality = $this->locality();
        $other = County::query()->create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);

        $this->actingAs($staff)->post(route('staff.properties.store'), $this->payload($locality, $seller, [
            'county_id' => $other->id,
        ]))->assertSessionHasErrors('locality_id');
    }

    public function test_staff_can_register_lamu_acres_inventory_without_a_linked_seller(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $locality = $this->locality();

        $payload = $this->payload($locality, overrides: [
            'title' => 'Agency beach plot',
            'owner_user_id' => null,
            'owner_relationship' => null,
            'marketing_role' => null,
        ]);
        unset($payload['owner_user_id'], $payload['owner_relationship'], $payload['marketing_role']);

        $this->actingAs($staff)
            ->post(route('staff.properties.store'), $payload)
            ->assertRedirect();

        $property = Property::query()->where('title', 'Agency beach plot')->firstOrFail();

        $this->assertSame(PropertyStatus::Published, $property->status);
        $this->assertTrue($property->isLamuAcresInventoryListing());
        $this->assertFalse($property->isStaffRepresentedListing());
        $this->assertSame('Lamu Acres', $property->seller_name);
        $this->assertSame(MarketingRole::AuthorizedMarketer, $property->marketing_role);
        $this->assertSame(0, $property->owners()->count());
    }

    public function test_staff_register_page_uses_full_layout_not_modal(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();

        $this->actingAs($staff)
            ->get(route('staff.properties.create'))
            ->assertOk()
            ->assertSee('Register land', false)
            ->assertSee('data-property-location-map', false)
            ->assertSee('Register and publish listing', false);
    }

    public function test_registering_staff_can_publish_a_draft_from_property_edit(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $locality = $this->locality();
        $registration = $this->payload($locality, overrides: [
            'title' => 'Draft beach plot',
            'owner_user_id' => null,
            'owner_relationship' => null,
            'marketing_role' => null,
        ]);
        unset($registration['owner_user_id'], $registration['owner_relationship'], $registration['marketing_role'], $registration['photos']);

        $property = app(SellerPortal::class)->registerByStaff($staff, $registration);
        app(PropertyMediaLibrary::class)->storePhoto($staff, $property, UploadedFile::fake()->image('plot.jpg'));

        $this->assertSame(PropertyStatus::Draft, $property->fresh()->status);
        $this->assertTrue($property->registeredByStaff($staff));

        $this->actingAs($staff)
            ->put(route('staff.properties.update', $property), $this->inventoryUpdatePayload($property) + [
                'listing_action' => 'publish',
            ])
            ->assertRedirect(route('staff.properties.show', $property));

        $this->assertSame(PropertyStatus::Published, $property->fresh()->status);
    }

    public function test_other_staff_cannot_publish_a_registering_staff_draft_from_property_edit(): void
    {
        $registrar = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $other = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $locality = $this->locality();
        $registration = $this->payload($locality, overrides: [
            'title' => 'Another draft plot',
            'owner_user_id' => null,
            'owner_relationship' => null,
            'marketing_role' => null,
        ]);
        unset($registration['owner_user_id'], $registration['owner_relationship'], $registration['marketing_role'], $registration['photos']);

        $property = app(SellerPortal::class)->registerByStaff($registrar, $registration);
        app(PropertyMediaLibrary::class)->storePhoto($registrar, $property, UploadedFile::fake()->image('plot.jpg'));

        $this->actingAs($other)
            ->put(route('staff.properties.update', $property), $this->inventoryUpdatePayload($property) + [
                'listing_action' => 'publish',
            ])
            ->assertForbidden();

        $this->assertSame(PropertyStatus::Draft, $property->fresh()->status);
    }

    public function test_only_registering_staff_sees_publish_on_draft_status_changes(): void
    {
        $registrar = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $other = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $property = Property::factory()->listedIn($this->locality())->create([
            'status' => PropertyStatus::Draft,
            'represented_by' => $registrar->id,
            'submitted_by' => null,
            'description' => 'Draft agency holding.',
            'marketing_role' => MarketingRole::AuthorizedMarketer,
        ]);

        $this->assertContains(
            PropertyStatus::Published,
            $property->allowedTransitionsFor($registrar),
        );
        $this->assertNotContains(
            PropertyStatus::Published,
            $property->allowedTransitionsFor($other),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function inventoryUpdatePayload(Property $property): array
    {
        $property->loadMissing('areaCounty', 'areaLocality');

        $payload = [
            'title' => $property->title,
            'description' => $property->description,
            'county_id' => $property->county_id,
            'locality_id' => $property->locality_id,
            'property_type' => $property->property_type->value,
            'land_use' => $property->land_use->value,
            'purpose' => $property->purpose->value,
            'marketing_role' => $property->marketing_role->value,
            'acreage' => $property->acreage,
            'offered_acreage' => $property->offered_acreage,
            'price_basis' => $property->price_basis->value,
        ];

        if ($property->priceBasis()->isPerAcre()) {
            $payload['price_rate'] = $property->price_rate;
        } else {
            $payload['price_amount'] = $property->price_amount;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Locality $locality, ?User $seller = null, array $overrides = []): array
    {
        $base = [
            'purpose' => ListingPurpose::Sale->value,
            'title' => 'Mokowe holding',
            'summary' => 'Coastal holding near Mokowe.',
            'description' => 'A recorded holding with access and clear boundaries.',
            'county_id' => $locality->county_id,
            'locality_id' => $locality->id,
            'property_type' => PropertyType::Agricultural->value,
            'land_use' => LandUse::Agricultural->value,
            'tenure' => LandTenure::Freehold->value,
            'ownership_structure' => OwnershipStructure::Individual->value,
            'parcel_identifier' => 'LAMU/MOK/100',
            'parcel_reference_type' => ParcelReferenceType::TitleNumber->value,
            'acreage' => 10,
            'offered_acreage' => 10,
            'public_latitude' => -2.2712,
            'public_longitude' => 40.9021,
            'restricted_latitude' => 9.8765432,
            'restricted_longitude' => 40.1234567,
            'sale' => [
                'price_basis' => PriceBasis::PerAcre->value,
                'amount' => 100_000,
            ],
            'photos' => [UploadedFile::fake()->image('plot.jpg')],
        ];

        if ($seller !== null) {
            $base['marketing_role'] = MarketingRole::Owner->value;
            $base['owner_user_id'] = $seller->id;
            $base['owner_relationship'] = OwnerRelationship::Owner->value;
        }

        return array_merge($base, $overrides);
    }

    private function locality(): Locality
    {
        $county = County::query()->create([
            'name' => 'Lamu',
            'slug' => 'lamu',
            'is_active' => true,
        ]);

        return Locality::query()->create([
            'county_id' => $county->id,
            'name' => 'Mokowe',
            'slug' => 'mokowe',
            'kind' => 'place',
            'is_active' => true,
        ]);
    }
}
