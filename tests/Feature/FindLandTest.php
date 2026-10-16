<?php

namespace Tests\Feature;

use App\Enums\ListingPurpose;
use App\Enums\PriceBasis;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\ResearchReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FindLandTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_find_land_cta_uses_the_configured_public_site_address(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $this->get('http://127.0.0.1:8000/land')->assertOk()
            ->assertSee('href="http://localhost:8000/find-land"', false);
    }

    public function test_guests_can_search_and_both_navigation_links_open_the_dedicated_page(): void
    {
        $this->get(route('find-land.index'))->assertOk()
            ->assertHeader('Referrer-Policy', 'origin')
            ->assertSee("referrerPolicy: 'origin'", false)
            ->assertSee('https://tile.openstreetmap.org/{z}/{x}/{y}.png', false)
            ->assertSee('Find Your Ideal Land')
            ->assertSee('Choose your preferred location on the map')
            ->assertSee('id="finder-map"', false)
            ->assertSee('type="hidden" id="finder-latitude"', false)
            ->assertDontSee('Use My Location')
            ->assertDontSee('Search point coordinates')
            ->assertDontSee('navigator.geolocation', false)
            ->assertSee('Request staff assistance')
            ->assertSee('The map could not load')
            ->assertDontSee('#find-land');
        $document = new \DOMDocument;
        @$document->loadHTML($this->get('/')->getContent());
        $links = (new \DOMXPath($document))->query('//nav//a[@href="'.route('find-land.index').'"]');
        $this->assertSame(2, $links->length);
    }

    public function test_only_available_published_listings_are_returned_with_existing_property_links(): void
    {
        $place = $this->place();
        $visible = Property::factory()->published()->listedIn($place)->create(['title' => 'Available approved acreage']);
        Property::factory()->listedIn($place)->create(['title' => 'Private draft acreage']);
        Property::factory()->unavailable()->listedIn($place)->create(['title' => 'Sold unavailable acreage']);
        Property::factory()->published()->listedIn($place)->create(['title' => 'Future publication', 'published_at' => now()->addDay()]);
        Property::factory()->published()->listedIn($place)->create(['title' => 'Withdrawn acreage', 'status' => PropertyStatus::Archived]);

        $this->get('/find-land')->assertOk()->assertSee('Available approved acreage')
            ->assertSee($visible->publicUrl(), false)->assertDontSee('Private draft acreage')
            ->assertDontSee('Sold unavailable acreage')->assertDontSee('Future publication')->assertDontSee('Withdrawn acreage');
    }

    public function test_administrative_location_and_keyword_filters_work_together(): void
    {
        $place = $this->place();
        $other = $this->place('Hindi');
        Property::factory()->published()->listedIn($place)->create(['title' => 'Mokowe garden']);
        Property::factory()->published()->listedIn($other)->create(['title' => 'Hindi garden']);

        $this->get('/find-land?county='.$place->county_id.'&locality='.$place->id.'&q=garden')
            ->assertOk()->assertSee('Mokowe garden')->assertDontSee('Hindi garden');
    }

    public function test_nearby_search_uses_great_circle_distance_and_ignores_administrative_boundaries(): void
    {
        $place = $this->place();
        $other = $this->place('Hindi');
        Property::factory()->published()->listedIn($other)->create(['title' => 'Closest across boundary', 'public_latitude' => 0, 'public_longitude' => 0.01]);
        Property::factory()->published()->listedIn($place)->create(['title' => 'Farther plot', 'public_latitude' => 0, 'public_longitude' => 0.04]);
        Property::factory()->published()->listedIn($place)->create(['title' => 'Outside radius plot', 'public_latitude' => 0, 'public_longitude' => 0.1]);

        $response = $this->get('/find-land?mode=nearby&latitude=0&longitude=0&radius=5&sort=nearest&locality='.$place->id);
        $response->assertOk()->assertSeeInOrder(['Closest across boundary', 'Farther plot'])
            ->assertDontSee('Outside radius plot')->assertSee('Approx. 1.1 km');
        $this->assertEqualsWithDelta(1.11195, $response->viewData('listings')->items()[0]['distance_km'], 0.001);
    }

    public function test_radius_boundary_does_not_silently_include_outside_alternatives(): void
    {
        $place = $this->place();
        Property::factory()->published()->listedIn($place)->create(['title' => 'Boundary opportunity', 'public_latitude' => 0, 'public_longitude' => 0.01]);

        $this->get('/find-land?mode=nearby&latitude=0&longitude=0&radius=1.1119')->assertOk()
            ->assertSee('No available land was found within this distance.')->assertDontSee('Boundary opportunity');
        $this->get('/find-land?mode=nearby&latitude=0&longitude=0&radius=1.112')->assertOk()->assertSee('Boundary opportunity');
    }

    public function test_combined_radius_budget_acreage_type_and_purpose_filters_match_real_offered_land(): void
    {
        $place = $this->place();
        $attributes = ['public_latitude' => 0, 'public_longitude' => 0.01, 'price_amount' => 2_000_000, 'acreage' => 20, 'offered_acreage' => 2, 'purpose' => ListingPurpose::Sale, 'property_type' => PropertyType::Agricultural];
        Property::factory()->published()->listedIn($place)->create([...$attributes, 'title' => 'Matching offered portion']);
        Property::factory()->published()->listedIn($place)->create([...$attributes, 'title' => 'Over budget', 'price_amount' => 4_000_000]);
        Property::factory()->published()->listedIn($place)->create([...$attributes, 'title' => 'Insufficient offered area', 'offered_acreage' => 0.5]);
        Property::factory()->published()->listedIn($place)->create([...$attributes, 'title' => 'Lease instead', 'purpose' => ListingPurpose::Lease]);

        $this->get('/find-land?mode=nearby&latitude=0&longitude=0&radius=5&purpose=sale&type=agricultural&max_price=3000000&min_acres=1&max_acres=3')
            ->assertOk()->assertSee('Matching offered portion')->assertDontSee('Over budget')->assertDontSee('Insufficient offered area')->assertDontSee('Lease instead');
    }

    public function test_sale_budget_compares_whole_offered_price_or_explicit_per_acre_equivalent(): void
    {
        $place = $this->place();
        Property::factory()->published()->listedIn($place)->create(['title' => 'Per acre quoted holding', 'price_basis' => PriceBasis::PerAcre, 'price_rate' => 1_000_000, 'price_amount' => 5_000_000, 'acreage' => 5]);

        $this->get('/find-land?purpose=sale&max_price=1500000')->assertOk()->assertDontSee('Per acre quoted holding');
        $this->get('/find-land?purpose=sale&budget_basis=per_acre&max_price=1500000')->assertOk()->assertSee('Per acre quoted holding');
    }

    public function test_lease_budgets_compare_monthly_equivalent_and_exclude_unknown_periods(): void
    {
        $place = $this->place();
        Property::factory()->published()->listedIn($place)->create(['title' => 'Annual rent matching', 'purpose' => ListingPurpose::Lease, 'price_basis' => PriceBasis::ParcelYear, 'price_amount' => 120_000]);
        Property::factory()->published()->listedIn($place)->create(['title' => 'Monthly rent too high', 'purpose' => ListingPurpose::Lease, 'price_basis' => PriceBasis::ParcelMonth, 'price_amount' => 120_000]);
        Property::factory()->published()->listedIn($place)->create(['title' => 'Unspecified rental period', 'purpose' => ListingPurpose::Lease, 'price_basis' => PriceBasis::Total, 'price_amount' => 5_000]);

        $this->get('/find-land?purpose=lease&max_price=11000')->assertOk()->assertSee('Annual rent matching')->assertDontSee('Monthly rent too high')->assertDontSee('Unspecified rental period');
    }

    #[TestWith(['latitude=91&longitude=0', 'latitude'])]
    #[TestWith(['latitude=0&longitude=-181', 'longitude'])]
    #[TestWith(['latitude=0', 'longitude'])]
    #[TestWith(['latitude[]=0&longitude=0', 'latitude'])]
    #[TestWith(['radius=-1', 'radius'])]
    #[TestWith(['radius=101', 'radius'])]
    #[TestWith(['min_acres=-2', 'min_acres'])]
    #[TestWith(['min_price=-1', 'min_price'])]
    #[TestWith(['purpose=sale&min_price=10&max_price=1', 'max_price'])]
    #[TestWith(['min_acres=10&max_acres=1', 'max_acres'])]
    #[TestWith(['max_price=100', 'purpose'])]
    #[TestWith(['sort=nearest', 'sort'])]
    #[TestWith(['sort=malicious', 'sort'])]
    public function test_invalid_search_parameters_are_rejected(string $query, string $field): void
    {
        $this->getJson('/find-land?'.$query)->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_missing_invalid_and_restricted_coordinates_are_not_used_for_proximity(): void
    {
        $place = $this->place();
        $private = Property::factory()->published()->listedIn($place)->create(['title' => 'Unmapped private holding', 'public_latitude' => null, 'public_longitude' => null]);
        $private->forceFill(['restricted_latitude' => 0.0001234, 'restricted_longitude' => 0.0005678])->save();
        Property::factory()->published()->listedIn($place)->create(['title' => 'Invalid public point', 'public_latitude' => 91, 'public_longitude' => 0]);

        $response = $this->get('/find-land?mode=nearby&latitude=0&longitude=0&radius=5');
        $response->assertOk()->assertDontSee('Unmapped private holding')->assertDontSee('Invalid public point')->assertDontSee('0.0001234')->assertDontSee('restricted_latitude');
        $this->get('/find-land')->assertOk()->assertSee('Unmapped private holding')->assertDontSee('Approx.')->assertDontSee('0.0005678');
    }

    public function test_public_points_are_rounded_and_no_private_fields_reach_the_page(): void
    {
        $place = $this->place();
        $property = Property::factory()->published()->listedIn($place)->create(['public_latitude' => -2.2712345, 'public_longitude' => 40.8712345]);
        $property->forceFill(['restricted_latitude' => -2.1234567, 'restricted_longitude' => 40.7654321, 'seller_name' => 'Confidential Owner Name', 'seller_contact' => 'Confidential Owner Contact'])->save();

        $this->get('/find-land')->assertOk()->assertDontSee('-2.2712345')->assertDontSee('-2.1234567')->assertDontSee('40.7654321')->assertDontSee('Confidential Owner Name')->assertDontSee('Confidential Owner Contact');
    }

    public function test_registered_centre_search_is_explicit_and_missing_centres_have_a_fallback(): void
    {
        $place = $this->place();
        $place->update(['latitude' => 0, 'longitude' => 0]);
        Property::factory()->published()->listedIn($place)->create(['title' => 'Centre nearby listing', 'public_latitude' => 0, 'public_longitude' => 0.01]);
        $this->get('/find-land?mode=nearby&locality='.$place->id)->assertOk()->assertSee('Mokowe registered centre')->assertSee('Centre nearby listing');
        $place->update(['latitude' => null, 'longitude' => null]);
        $this->getJson('/find-land?mode=nearby&locality='.$place->id)->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->get('/find-land?locality='.$place->id)->assertOk()->assertSee('Centre nearby listing');
    }

    public function test_pagination_retains_search_filters_and_map_markers_match_the_current_page(): void
    {
        $place = $this->place();
        Property::factory()->count(14)->published()->listedIn($place)->create(['public_latitude' => 0, 'public_longitude' => 0.01, 'price_amount' => 2_000_000, 'acreage' => 2]);

        $response = $this->get('/find-land?mode=nearby&latitude=0&longitude=0&radius=5&purpose=sale&max_price=3000000&sort=nearest');
        $response->assertOk();
        $this->assertCount(12, $response->viewData('listings')->items());
        $this->assertCount(12, $response->viewData('markers'));
        $this->assertStringContainsString('radius=5', $response->viewData('listings')->nextPageUrl());
        $this->assertStringContainsString('max_price=3000000', $response->viewData('listings')->nextPageUrl());
        $pageTwo = $this->get('/find-land?mode=nearby&latitude=0&longitude=0&radius=5&purpose=sale&max_price=3000000&sort=nearest&page=2');
        $pageTwo->assertOk();
        $this->assertCount(2, $pageTwo->viewData('markers'));
    }

    public function test_price_and_acreage_sorting_use_the_selected_comparison_basis(): void
    {
        $place = $this->place();
        Property::factory()->published()->listedIn($place)->create(['title' => 'Small high rate', 'price_amount' => 2_000_000, 'acreage' => 1]);
        Property::factory()->published()->listedIn($place)->create(['title' => 'Large low rate', 'price_amount' => 3_000_000, 'acreage' => 5]);

        $this->get('/find-land?purpose=sale&sort=price_asc')->assertOk()->assertSeeInOrder(['Small high rate', 'Large low rate']);
        $this->get('/find-land?purpose=sale&budget_basis=per_acre&sort=price_asc')->assertOk()->assertSeeInOrder(['Large low rate', 'Small high rate']);
        $this->get('/find-land?sort=acres_desc')->assertOk()->assertSeeInOrder(['Large low rate', 'Small high rate']);
    }

    public function test_radius_search_crosses_the_antimeridian_without_using_the_long_way_round(): void
    {
        $place = $this->place();
        Property::factory()->published()->listedIn($place)->create(['title' => 'Across date line', 'public_latitude' => 0, 'public_longitude' => -179.99]);

        $response = $this->get('/find-land?mode=nearby&latitude=0&longitude=179.99&radius=5');
        $response->assertOk()->assertSee('Across date line');
        $this->assertEqualsWithDelta(2.2239, $response->viewData('listings')->items()[0]['distance_km'], 0.001);
    }

    public function test_county_and_locality_must_belong_together(): void
    {
        $place = $this->place();
        $otherCounty = County::query()->create(['name' => 'Kilifi', 'slug' => 'kilifi']);

        $this->getJson('/find-land?county='.$otherCounty->id.'&locality='.$place->id)
            ->assertUnprocessable()->assertJsonValidationErrors('locality');
    }

    public function test_only_released_location_research_is_linked(): void
    {
        $place = $this->place();
        $author = User::factory()->create();
        foreach (['published', 'draft'] as $status) {
            $report = ResearchReport::query()->create(['reference' => 'REPORT-'.$status, 'slug' => 'report-'.$status, 'title' => ucfirst($status).' local research', 'summary' => 'Area research', 'body' => 'Area facts', 'publication_status' => $status, 'published_at' => now()->subDay(), 'created_by' => $author->id]);
            $report->localities()->attach($place);
        }

        $this->get('/find-land?locality='.$place->id)->assertOk()->assertSee('Published local research')->assertDontSee('Draft local research');
    }

    public function test_keyword_output_is_escaped_and_sold_portions_reduce_available_acreage(): void
    {
        $place = $this->place();
        $parent = Property::factory()->published()->listedIn($place)->create(['title' => 'Partly sold parent', 'acreage' => 5]);
        Property::factory()->listedIn($place)->create(['parent_id' => $parent->id, 'acreage' => 4, 'status' => PropertyStatus::Sold]);
        $this->get('/find-land?min_acres=2')->assertOk()->assertDontSee('Partly sold parent');
        $this->get('/find-land?q='.urlencode('<script>alert(1)</script>'))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    private function place(string $name = 'Mokowe'): Locality
    {
        $county = County::query()->firstOrCreate(['slug' => 'lamu'], ['name' => 'Lamu']);

        return Locality::query()->firstOrCreate(['slug' => strtolower($name), 'county_id' => $county->id], ['name' => $name]);
    }
}
