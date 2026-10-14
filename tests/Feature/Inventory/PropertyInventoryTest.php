<?php

namespace Tests\Feature\Inventory;

use App\Enums\PropertyStatus;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\User;
use Tests\Feature\Foundation\FoundationTestCase;

class PropertyInventoryTest extends FoundationTestCase
{
    public function test_staff_can_record_a_property_and_price_per_acre_is_exact(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $locality = $this->locality();

        $this->actingAs($buyer)->post(route('staff.properties.store'), $this->payload($locality))->assertForbidden();

        $this->actingAs($staff)->post(route('staff.properties.store'), $this->payload($locality, [
            'acreage' => 10,
            'price_amount' => 1_000_000,
            'restricted_latitude' => 9.8765432,
            'restricted_longitude' => 40.1234567,
        ]))->assertRedirect(route('staff.properties.index'));

        $property = Property::query()->where('title', 'Mokowe holding')->firstOrFail();

        $this->assertSame(100_000, $property->pricePerAcre());
        $this->assertSame(PropertyStatus::Draft, $property->status);
        $this->assertSame('owner', $property->marketing_role->value);
        $this->assertNotNull($property->reference);
        $this->assertTrue(PropertyAudit::query()->where('property_id', $property->id)->where('action', 'created')->exists());

        $this->actingAs($staff)->put(route('staff.properties.transition', $property), [
            'status' => 'sold',
        ])->assertSessionHasErrors('status');

        $this->actingAs($staff)->put(route('staff.properties.transition', $property), ['status' => 'review'])->assertRedirect();
        $this->actingAs($staff)->put(route('staff.properties.transition', $property), ['status' => 'published'])->assertRedirect();

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

        $this->actingAs($staff)->post(route('staff.properties.store'), $this->payload($locality, [
            'title' => 'North portion',
            'acreage' => 4,
            'price_amount' => 400_000,
            'parent_id' => $parent->id,
            'portion_label' => 'North',
        ]))->assertRedirect();

        $portion = Property::query()->where('title', 'North portion')->firstOrFail();
        $this->assertSame(100_000, $portion->pricePerAcre());

        $this->actingAs($staff)->put(route('staff.properties.transition', $portion), ['status' => 'review']);
        $this->actingAs($staff)->put(route('staff.properties.transition', $portion), ['status' => 'published']);
        $this->actingAs($staff)->post(route('staff.properties.sell-portion', $portion))->assertRedirect();

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
            'price_amount' => 500_000,
            'marketing_role' => 'owner',
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
