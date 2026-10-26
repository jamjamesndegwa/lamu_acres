<?php

namespace Tests\Feature\Inventory;

use App\Enums\AuthorityScope;
use App\Enums\AuthorityStatus;
use App\Enums\MarketingRole;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PriceBasis;
use App\Enums\PropertyStatus;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\User;
use App\Services\Seller\SellerPortal;
use Tests\Feature\Foundation\FoundationTestCase;

class PropertyInventoryTest extends FoundationTestCase
{
    public function test_staff_can_record_a_property_and_price_per_acre_is_exact(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $locality = $this->locality();

        $this->actingAs($buyer)->post(route('staff.properties.store'), $this->payload($locality))->assertForbidden();

        $response = $this->actingAs($staff)->post(route('staff.properties.store'), $this->payload($locality, [
            'acreage' => 10,
            'sale' => [
                'price_basis' => PriceBasis::PerAcre->value,
                'amount' => 100_000,
            ],
            'restricted_latitude' => 9.8765432,
            'restricted_longitude' => 40.1234567,
        ]));

        $property = Property::query()->where('title', 'Mokowe holding')->firstOrFail();
        $response->assertRedirect(route('staff.properties.show', $property));

        $this->assertSame(100_000, $property->pricePerAcre());
        $this->assertSame(PropertyStatus::Draft, $property->status);
        $this->assertSame(MarketingRole::Owner, $property->marketing_role);
        $this->assertNotNull($property->reference);
        $this->assertTrue(PropertyAudit::query()->where('property_id', $property->id)->where('action', 'registered by staff')->exists());

        $this->actingAs($staff)->put(route('staff.properties.transition', $property), [
            'status' => 'sold',
        ])->assertSessionHasErrors('status');

        $this->actingAs($staff)->put(route('staff.properties.transition', $property), ['status' => 'review'])->assertRedirect();
        $this->actingAs($staff)->put(route('staff.properties.transition', $property), ['status' => 'published'])->assertSessionHasErrors('listing');
        $this->assertSame(PropertyStatus::Review, $property->refresh()->status);

        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $property->owners()->create([
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);
        $property->authorities()->create([
            'seller_id' => $seller->id,
            'scope' => AuthorityScope::Sale,
            'status' => AuthorityStatus::Active,
            'terms' => 'Authorised to market this property for sale.',
        ]);
        app(SellerPortal::class)->verifyLink($property->owners()->firstOrFail(), $staff, null, 'Test review: identity and authority match.', true);
        $this->attachPublicPhoto($property, $staff);
        $this->clearCriticalVerification($property, $staff);
        $this->actingAs($staff)->post(route('staff.properties.listing', $property))->assertSessionHasNoErrors();

        $property->refresh();
        $this->assertSame(PropertyStatus::Published, $property->status);
        $this->assertNotNull($property->published_at);

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
        $locality = $this->locality();
        $other = County::query()->create(['name' => 'Other', 'slug' => 'other', 'is_active' => true]);

        $this->actingAs($staff)->post(route('staff.properties.store'), $this->payload($locality, [
            'county_id' => $other->id,
        ]))->assertSessionHasErrors('locality_id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Locality $locality, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Mokowe holding',
            'description' => 'A recorded holding.',
            'county_id' => $locality->county_id,
            'locality_id' => $locality->id,
            'property_type' => 'agricultural',
            'land_use' => 'agricultural',
            'purpose' => 'sale',
            'acreage' => 5,
            'marketing_role' => MarketingRole::Owner->value,
            'sale' => [
                'price_basis' => PriceBasis::Total->value,
                'amount' => 500_000,
            ],
        ], $overrides);
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
