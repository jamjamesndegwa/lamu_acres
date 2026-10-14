<?php

namespace Tests\Feature\Geography;

use App\Models\County;
use App\Models\InfrastructureFeature;
use App\Models\Locality;
use App\Models\MapLayer;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Tests\Feature\Foundation\FoundationTestCase;

class GeographyLayerTest extends FoundationTestCase
{
    public function test_map_layers_draw_stored_points_and_leave_missing_boundaries_off_the_map(): void
    {
        $this->seed(GeographySeeder::class);
        $admin = User::factory()->withRoles('admin')->create();
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->get(route('admin.geography.layers.index'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.geography.layers.index'))
            ->assertOk()
            ->assertSee('Map layers')
            ->assertSee('County boundaries')
            ->assertSee('Named places')
            ->assertSee('Infrastructure reference')
            ->assertSee('Shela')
            ->assertSee('Mkomani')
            ->assertSee('Boundary not stored')
            ->assertSee('Named point not stored')
            ->assertDontSee('Unavailable')
            ->assertDontSee('POLYGON');

        MapLayer::query()->where('slug', 'county-boundaries')->firstOrFail();
        $this->assertNull(MapLayer::query()->where('slug', 'named-places')->firstOrFail()->boundary_wkt);

        $this->actingAs($admin)
            ->put(route('admin.geography.layers.update', 'named-places'), [
                'name' => 'Named places',
                'kind' => 'locality_points',
                'source' => 'Stored geography records',
                'description' => 'Markers only.',
                'is_visible' => '1',
            ])
            ->assertRedirect(route('admin.geography.layers.index'));

        $this->assertSame('Markers only.', MapLayer::query()->where('slug', 'named-places')->firstOrFail()->description);
        $this->assertNull(MapLayer::query()->where('slug', 'named-places')->firstOrFail()->boundary_wkt);
    }

    public function test_a_boundary_layer_does_not_keep_a_pasted_outline(): void
    {
        $admin = User::factory()->withRoles('admin')->create();

        $this->actingAs($admin)
            ->post(route('admin.geography.layers.store'), [
                'name' => 'Extra county outline',
                'kind' => 'county_boundaries',
                'source' => 'Unchecked sketch',
                'boundary_wkt' => 'POLYGON((40.8 -2.3, 40.9 -2.3, 40.9 -2.2, 40.8 -2.3))',
                'is_visible' => '1',
            ])
            ->assertRedirect(route('admin.geography.layers.index'));

        $layer = MapLayer::query()->where('slug', 'extra-county-outline')->firstOrFail();
        $this->assertNull($layer->boundary_wkt);
        $this->assertNull($layer->latitude);

        $this->actingAs($admin)
            ->post(route('admin.geography.layers.store'), [
                'name' => 'Extra county outline',
                'kind' => 'custom',
                'source' => 'Unchecked sketch',
                'is_visible' => '1',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, MapLayer::query()->where('slug', 'extra-county-outline')->count());
    }

    public function test_infrastructure_references_can_be_added_filtered_and_shown_on_a_property(): void
    {
        $this->seed(GeographySeeder::class);
        $admin = User::factory()->withRoles('admin')->create();
        $staff = User::factory()->withRoles('staff')->create();
        $county = County::query()->where('slug', 'lamu')->firstOrFail();
        $shela = Locality::query()->where('slug', 'shela')->firstOrFail();

        $this->actingAs($staff)->get(route('admin.geography.infrastructure.index'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.geography.infrastructure.index'))
            ->assertOk()
            ->assertSee('No infrastructure references')
            ->assertSee('No verified boundary or named point is stored')
            ->assertDontSee('Unavailable');

        $this->actingAs($admin)
            ->post(route('admin.geography.infrastructure.store'), [
                'name' => 'Mokowe jetty',
                'kind' => 'jetty',
                'county_id' => $county->id,
                'locality_id' => $shela->id,
                'source' => 'Harbour notice',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.geography.infrastructure.index'));

        $jetty = InfrastructureFeature::query()->where('slug', 'mokowe-jetty')->firstOrFail();
        $this->assertNull($jetty->boundary_wkt);
        $this->assertFalse($jetty->hasPoint());

        $this->actingAs($admin)
            ->post(route('admin.geography.infrastructure.store'), [
                'name' => 'Mokowe jetty',
                'kind' => 'jetty',
                'county_id' => $county->id,
                'source' => 'Harbour notice',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, InfrastructureFeature::query()->where('slug', 'mokowe-jetty')->count());

        $this->actingAs($admin)
            ->put(route('admin.geography.infrastructure.update', $jetty), [
                'name' => 'Mokowe jetty',
                'kind' => 'jetty',
                'county_id' => $county->id,
                'locality_id' => $shela->id,
                'source' => 'Harbour notice',
                'latitude' => '-2.23',
                'longitude' => '40.85',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.geography.infrastructure.index'));

        $this->actingAs($admin)
            ->post(route('admin.geography.infrastructure.store'), [
                'name' => 'Shela water point',
                'kind' => 'water',
                'county_id' => $county->id,
                'source' => 'County water note',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.geography.infrastructure.index'));

        $this->actingAs($admin)
            ->get(route('admin.geography.infrastructure.index', ['kind' => 'water']))
            ->assertOk()
            ->assertSee('Shela water point')
            ->assertDontSee('Mokowe jetty');

        $this->actingAs($admin)
            ->get(route('admin.geography.layers.index', ['q' => 'Mokowe jetty']))
            ->assertOk()
            ->assertSee('Infrastructure reference')
            ->assertSee('Mokowe jetty')
            ->assertDontSee('POLYGON');

        $property = Property::factory()->create(['county_id' => $county->id]);
        $officer = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();

        $this->actingAs($officer)
            ->get(route('staff.properties.show', [$property, 'tab' => 'marketing']))
            ->assertOk()
            ->assertSee('Mokowe jetty')
            ->assertSee('boundary not stored')
            ->assertSee('Harbour notice');
    }
}
