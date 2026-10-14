<?php

namespace Tests\Feature\Public;

use App\Enums\ListingPurpose;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLandTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_public_sections_and_search(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Lamu Acres')
            ->assertSee('Explore Land')
            ->assertSee('Featured land')
            ->assertSee('No featured land is published yet.')
            ->assertSee('Explore Lamu by location')
            ->assertSee('Find Land for Me')
            ->assertSee('Premium Investor Intelligence')
            ->assertSee('Verification and trust')
            ->assertSee('Brokers and sellers')
            ->assertSee('name="q"', false)
            ->assertSee(route('land.index'), false);
    }

    public function test_homepage_shows_only_featured_published_land_and_real_location_counts(): void
    {
        $shela = $this->locality('lamu', 'Lamu', 'shela', 'Shela');
        $hindi = $this->locality('lamu', 'Lamu', 'hindi', 'Hindi');

        Property::factory()->published()->featured()->listedIn($shela)->create([
            'title' => 'Featured Shela acreage',
        ]);
        Property::factory()->published()->listedIn($hindi)->create([
            'title' => 'Ordinary Hindi acreage',
        ]);
        Property::factory()->unavailable()->listedIn($shela)->create([
            'title' => 'Sold Shela acreage',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Featured Shela acreage')
            ->assertDontSee('Ordinary Hindi acreage')
            ->assertDontSee('Sold Shela acreage')
            ->assertDontSee('No featured land is published yet.')
            ->assertSee('Shela (1)')
            ->assertSee('Hindi (1)')
            ->assertSee('Lamu (2)');
    }

    public function test_directory_is_public_and_lists_only_available_published_land(): void
    {
        $shela = $this->locality('lamu', 'Lamu', 'shela', 'Shela');

        Property::factory()->published()->listedIn($shela)->create(['title' => 'Visible Shela plot']);
        Property::factory()->listedIn($shela)->create(['title' => 'Hidden draft plot']);
        Property::factory()->unavailable()->listedIn($shela)->create(['title' => 'Hidden sold plot']);
        Property::factory()->published()->listedIn($shela)->create([
            'title' => 'Hidden archived plot',
            'status' => PropertyStatus::Archived,
        ]);
        Property::factory()->published()->listedIn($shela)->create([
            'title' => 'Hidden scheduled plot',
            'published_at' => now()->addDay(),
        ]);

        $this->get('/land')
            ->assertOk()
            ->assertSee('Land in Lamu')
            ->assertSee('Visible Shela plot')
            ->assertSee('Shela (1)')
            ->assertDontSee('Hidden draft plot')
            ->assertDontSee('Hidden sold plot')
            ->assertDontSee('Hidden archived plot')
            ->assertDontSee('Hidden scheduled plot');
    }

    public function test_directory_filters_sorts_and_paginates_with_shareable_parameters(): void
    {
        $shela = $this->locality('lamu', 'Lamu', 'shela', 'Shela');
        $manda = $this->locality('lamu', 'Lamu', 'manda', 'Manda');

        Property::factory()->published()->listedIn($shela)->create([
            'title' => 'Shela lease holding',
            'purpose' => ListingPurpose::Lease,
            'property_type' => PropertyType::Residential,
            'price_amount' => 2_000_000,
            'acreage' => 2,
        ]);
        Property::factory()->published()->listedIn($manda)->create([
            'title' => 'Manda sale block',
            'purpose' => ListingPurpose::Sale,
            'property_type' => PropertyType::Agricultural,
            'price_amount' => 8_000_000,
            'acreage' => 40,
        ]);

        $this->get('/land?q=Shela')
            ->assertOk()
            ->assertSee('Shela lease holding')
            ->assertDontSee('Manda sale block');

        $this->get('/land/lamu')
            ->assertOk()
            ->assertSee('Shela lease holding')
            ->assertSee('Manda sale block');

        $this->get('/land/lamu/shela')
            ->assertOk()
            ->assertSee('Land in Shela')
            ->assertSee('Shela lease holding')
            ->assertDontSee('Manda sale block');

        $this->get('/land?purpose=lease&type=residential&min_price=1000000&max_price=3000000&min_acres=1&max_acres=5&max_price_per_acre=1500000')
            ->assertOk()
            ->assertSee('Shela lease holding')
            ->assertDontSee('Manda sale block');

        Property::factory()->published()->listedIn($shela)->create([
            'title' => 'Wide cheap acre',
            'price_amount' => 1_000_000,
            'acreage' => 10,
        ]);
        Property::factory()->published()->listedIn($shela)->create([
            'title' => 'Tight expensive acre',
            'price_amount' => 1_000_000,
            'acreage' => 1,
        ]);

        $this->get('/land?max_price_per_acre=200000')
            ->assertOk()
            ->assertSee('Wide cheap acre')
            ->assertDontSee('Tight expensive acre');

        $this->get('/land?sort=price_asc')
            ->assertOk()
            ->assertSeeInOrder(['Shela lease holding', 'Manda sale block']);

        $this->get('/land?purpose=not-a-purpose')
            ->assertOk()
            ->assertSee('Shela lease holding')
            ->assertSee('Manda sale block');

        foreach (range(1, 13) as $number) {
            Property::factory()->published()->listedIn($shela)->create([
                'title' => sprintf('Paged acre %02d', $number),
                'published_at' => now()->subMinutes(30 - $number),
            ]);
        }

        $this->get('/land?page=2')
            ->assertOk()
            ->assertSee('Paged acre 01')
            ->assertDontSee('Paged acre 13')
            ->assertSee('Previous');
    }

    public function test_public_listing_page_hides_unpublished_land_and_private_fields(): void
    {
        $shela = $this->locality('lamu', 'Lamu', 'shela', 'Shela');
        $listing = Property::factory()->published()->listedIn($shela)->create([
            'title' => 'Open Shela listing',
            'summary' => 'A published summary.',
        ]);
        $draft = Property::factory()->listedIn($shela)->create(['title' => 'Closed draft listing']);

        $this->get(route('land.show', ['lamu', 'shela', $listing]))
            ->assertOk()
            ->assertSee('Open Shela listing')
            ->assertSee('A published summary.')
            ->assertDontSee('latitude')
            ->assertDontSee('longitude')
            ->assertDontSee('Seller');

        $this->get(route('land.show', ['lamu', 'shela', $draft]))->assertNotFound();
        $this->get(route('land.show', ['lamu', 'manda', $listing]))->assertNotFound();
    }

    public function test_empty_directory_does_not_invent_listings(): void
    {
        $this->get('/land')
            ->assertOk()
            ->assertSee('Land in Lamu')
            ->assertSee('No land has been published yet.')
            ->assertSee('0 available listings');
    }

    private function locality(string $countySlug, string $countyName, string $localitySlug, string $localityName): Locality
    {
        $county = County::query()->firstOrCreate(
            ['slug' => $countySlug],
            ['name' => $countyName],
        );

        return Locality::query()->firstOrCreate(
            ['county_id' => $county->id, 'slug' => $localitySlug],
            ['name' => $localityName],
        );
    }
}
