<?php

namespace Tests\Feature\Public;

use App\Enums\LandTenure;
use App\Enums\ListingPurpose;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PriceBasis;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\User;
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
            ->assertSee('Explore Land by Location')
            ->assertSee('Explore by Location')
            ->assertSee('Find Land for Me')
            ->assertSee('Investor Intelligence')
            ->assertSee('Why Choose Lamu Acres')
            ->assertSee('Are You a Broker or Land Owner?')
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
            ->assertSee('Shela')
            ->assertSee('Hindi');
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
            ->assertSee('Listing map')
            ->assertSee('id="land-directory-map"', false)
            ->assertSee('Green dots match the listings shown for your current filters.')
            ->assertDontSee('Overview map of the Lamu area')
            ->assertSee('Visible Shela plot')
            ->assertSeeInOrder(['<span class="flex-1">Shela</span>', '1 <span class="sr-only">published listing</span>'], false)
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
            ->assertDontSee('Manda sale block')
            ->assertSee('land-directory-map-markers', false)
            ->assertSee('Shela lease holding', false);

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

    public function test_public_sale_and_lease_terms_display_and_absence_of_restricted_data(): void
    {
        $shela = $this->locality('lamu', 'Lamu', 'shela', 'Shela');
        $seller = User::factory()->create(['name' => 'Secret Landowner', 'email' => 'private@seller.test']);

        $saleListing = Property::factory()->published()->listedIn($shela)->create([
            'title' => 'Prime Shela Sand Dunes',
            'summary' => 'Beautiful dunes for development.',
            'purpose' => ListingPurpose::Sale,
            'price_basis' => PriceBasis::PerAcre,
            'price_rate' => 2_500_000,
            'price_amount' => 10_000_000,
            'acreage' => 4,
            'offered_acreage' => 4,
            'price_negotiable' => true,
            'tenure' => LandTenure::Freehold,
            'landmark' => 'Near Peponi Hotel',
            'parcel_identifier' => 'SECRET-PARCEL-999',
            'restricted_latitude' => -2.2981,
            'restricted_longitude' => 40.9134,
            'submitted_by' => $seller->id,
            'listing_terms' => [
                'possession_timing' => 'Immediate upon final settlement',
                'current_use' => 'Undeveloped beachfront',
            ],
        ]);
        $saleListing->owners()->create([
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);

        $this->get(route('land.show', ['lamu', 'shela', $saleListing]))
            ->assertOk()
            ->assertSee('Prime Shela Sand Dunes')
            ->assertSee('Sale')
            ->assertSee('Asking price')
            ->assertSee('Negotiable')
            ->assertSee('Freehold')
            ->assertSee('Near Peponi Hotel')
            ->assertSee('Immediate upon final settlement')
            ->assertDontSee('SECRET-PARCEL-999')
            ->assertDontSee('-2.2981')
            ->assertDontSee('40.9134')
            ->assertDontSee('Secret Landowner')
            ->assertDontSee('private@seller.test');

        $leaseListing = Property::factory()->published()->listedIn($shela)->lease(PriceBasis::ParcelMonth)->create([
            'title' => 'Shela Beachfront Lease',
            'summary' => 'Long-term residential lease.',
            'acreage' => 2,
            'price_rate' => 150_000,
            'price_amount' => 150_000,
            'lease_min_months' => 24,
            'lease_max_months' => 60,
            'parcel_identifier' => 'SECRET-LEASE-777',
            'restricted_latitude' => -2.2985,
            'restricted_longitude' => 40.9140,
            'listing_terms' => [
                'permitted_use' => 'Eco-lodge or private villa',
                'payment_frequency' => 'Quarterly in advance',
            ],
        ]);

        $this->get(route('land.show', ['lamu', 'shela', $leaseListing]))
            ->assertOk()
            ->assertSee('Shela Beachfront Lease')
            ->assertSee('Lease')
            ->assertSee('Asking rent')
            ->assertSee('24 to 60 months')
            ->assertSee('Eco-lodge or private villa')
            ->assertSee('Quarterly in advance')
            ->assertDontSee('SECRET-LEASE-777')
            ->assertDontSee('-2.2985')
            ->assertDontSee('40.9140');
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
