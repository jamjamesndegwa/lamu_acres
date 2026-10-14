<?php

namespace Tests\Feature\Geography;

use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Tests\Feature\Foundation\FoundationTestCase;

class GeographyAdminTest extends FoundationTestCase
{
    public function test_only_geography_administrators_can_open_the_directory(): void
    {
        $this->seed(GeographySeeder::class);
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = User::factory()->withRoles('staff')->create();
        $admin = User::factory()->withRoles('admin')->create();

        $this->actingAs($buyer)->get(route('admin.geography.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.geography.counties.show', 'lamu'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('admin.geography.index'))
            ->assertOk()
            ->assertSee('Lamu')
            ->assertSee('Not stored');
    }

    public function test_the_directory_filters_by_county_code_place_and_alias(): void
    {
        $this->seed(GeographySeeder::class);
        $admin = User::factory()->withRoles('admin')->create();
        $shela = Locality::query()->where('slug', 'shela')->firstOrFail();
        Property::factory()->published()->listedIn($shela)->create();

        $this->actingAs($admin)
            ->get(route('admin.geography.index', ['q' => '005']))
            ->assertOk()
            ->assertSee('Lamu')
            ->assertSee('1 published');

        $this->actingAs($admin)
            ->get(route('admin.geography.index', ['q' => 'Amu']))
            ->assertOk()
            ->assertSee('Lamu Island')
            ->assertDontSee('Tana River');

        $this->actingAs($admin)
            ->get(route('admin.geography.index', ['q' => 'No such county']))
            ->assertOk()
            ->assertSee('No counties match');
    }

    public function test_a_county_can_be_added_and_edited_without_a_duplicate_or_invented_boundary(): void
    {
        $admin = User::factory()->withRoles('admin')->create();
        $this->seed(GeographySeeder::class);

        $this->actingAs($admin)
            ->post(route('admin.geography.counties.store'), [
                'name' => 'Lamu',
                'code' => '099',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, County::query()->where('slug', 'lamu')->count());

        $this->actingAs($admin)
            ->post(route('admin.geography.counties.store'), [
                'name' => 'Tana River',
                'code' => '004',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.geography.index'));

        $county = County::query()->where('slug', 'tana-river')->firstOrFail();
        $this->assertNull($county->boundary_wkt);
        $this->assertFalse($county->hasPoint());

        $this->actingAs($admin)
            ->put(route('admin.geography.counties.update', $county), [
                'name' => 'Tana River',
                'code' => '004',
                'latitude' => '-2.5',
                'longitude' => '40.2',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.geography.index'));

        $county->refresh();
        $this->assertTrue($county->hasPoint());
        $this->assertNull($county->boundary_wkt);

        $this->actingAs($admin)
            ->get(route('admin.geography.counties.show', $county))
            ->assertOk()
            ->assertSee('Tana River')
            ->assertSee('Not stored')
            ->assertSee('No verified boundary is stored')
            ->assertDontSee('POLYGON');
    }

    public function test_localities_keep_their_hierarchy_and_reject_duplicate_names_and_aliases(): void
    {
        $this->seed(GeographySeeder::class);
        $admin = User::factory()->withRoles('admin')->create();
        $county = County::query()->where('slug', 'lamu')->firstOrFail();
        $west = Locality::query()->where('slug', 'lamu-west')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.geography.counties.show', ['county' => $county, 'kind' => 'ward', 'q' => 'Hindi']))
            ->assertOk()
            ->assertSee('Hindi')
            ->assertSee('Lamu West')
            ->assertSee('boundary not stored')
            ->assertDontSee('POLYGON');

        $this->actingAs($admin)
            ->post(route('admin.geography.localities.store', $county), [
                'name' => 'Bargoni',
                'kind' => 'place',
                'parent_id' => $west->id,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.geography.counties.show', $county));

        $place = Locality::query()->where('slug', 'bargoni')->firstOrFail();
        $this->assertSame($west->id, $place->parent_id);
        $this->assertNull($place->boundary_wkt);

        $this->actingAs($admin)
            ->post(route('admin.geography.localities.store', $county), [
                'name' => 'Bargoni',
                'kind' => 'place',
                'parent_id' => $west->id,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Locality::query()->where('slug', 'bargoni')->count());

        $this->actingAs($admin)
            ->put(route('admin.geography.localities.update', $place), [
                'name' => 'Bargoni',
                'kind' => 'place',
                'parent_id' => $west->id,
                'latitude' => '-2.11',
                'longitude' => '40.61',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.geography.counties.show', $county));

        $this->actingAs($admin)
            ->post(route('admin.geography.aliases.store', $place), ['name' => 'Bargoni town'])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.geography.aliases.store', $place), ['name' => 'Bargoni town'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $place->aliases()->count());
    }
}
