<?php

namespace Tests\Feature\Geography;

use App\Models\Locality;
use App\Services\Geography\LocationDirectory;
use Database\Seeders\GeographySeeder;
use Tests\Feature\Foundation\FoundationTestCase;

class LocationSearchTest extends FoundationTestCase
{
    public function test_lamu_places_keep_their_parent_relationships_and_have_no_invented_boundaries(): void
    {
        $this->seed(GeographySeeder::class);

        $hindi = Locality::query()->where('slug', 'hindi')->firstOrFail();
        $mokowe = Locality::query()->where('slug', 'mokowe')->firstOrFail();
        $mpeketoni = Locality::query()->where('slug', 'mpeketoni')->firstOrFail();
        $witu = Locality::query()->where('slug', 'witu')->firstOrFail();

        $this->assertSame('Lamu West', $hindi->parent->name);
        $this->assertSame('Lamu West', $mokowe->parent->name);
        $this->assertSame('Bahari', $mpeketoni->parent->name);
        $this->assertSame('ward', $witu->kind->value);
        $this->assertNull($hindi->boundary_wkt);
        $this->assertNull($mokowe->boundary_wkt);
        $this->assertNull($mpeketoni->boundary_wkt);
        $this->assertNull($witu->boundary_wkt);
        $this->assertNotNull($hindi->latitude);
    }

    public function test_aliases_and_inactive_places_follow_search_rules(): void
    {
        $this->seed(GeographySeeder::class);

        $directory = app(LocationDirectory::class);
        $matches = $directory->search('Shella');

        $this->assertTrue($matches->contains(fn (Locality $locality): bool => $locality->slug === 'shela'));

        $hidden = Locality::query()->where('slug', 'kiunga')->firstOrFail();
        $hidden->forceFill(['is_active' => false])->save();

        $this->assertFalse($directory->search('Kiunga')->contains(fn (Locality $locality): bool => $locality->slug === 'kiunga'));
    }

    public function test_nearby_search_orders_named_points_and_uses_mysql_spatial_sql(): void
    {
        $this->seed(GeographySeeder::class);

        $directory = app(LocationDirectory::class);
        $nearby = $directory->nearby(-2.16, 40.80, 15);

        $this->assertSame('hindi', $nearby->first()->slug);
        $this->assertFalse($nearby->contains(fn (Locality $locality): bool => $locality->slug === 'witu'));
        $this->assertStringContainsString('ST_Distance_Sphere', $directory->distanceExpression());
    }
}
