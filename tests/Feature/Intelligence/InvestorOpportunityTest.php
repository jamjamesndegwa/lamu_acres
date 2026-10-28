<?php

namespace Tests\Feature\Intelligence;

use App\Models\County;
use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\ImageryScene;
use App\Models\Locality;
use App\Models\PlanningRecord;
use App\Models\Property;
use App\Models\ResearchReport;
use App\Models\User;
use App\Services\Intelligence\PublicationDesk;
use Tests\Feature\Foundation\FoundationTestCase;

class InvestorOpportunityTest extends FoundationTestCase
{
    public function test_active_subscriber_can_browse_opportunities_and_open_intelligence_overview(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $locality = $this->locality('shela');
        $property = Property::factory()->published()->listedIn($locality)->create([
            'title' => 'Shela investor plot',
            'price_amount' => 4_000_000,
            'acreage' => 2,
        ]);

        $this->actingAs($subscriber)->get(route('investor.opportunities.index'))
            ->assertOk()
            ->assertSee('Shela investor plot', false)
            ->assertSee('Investment Opportunities', false);

        $this->actingAs($subscriber)->get(route('investor.opportunities.show', $property))
            ->assertOk()
            ->assertSee('Area price intelligence', false)
            ->assertSee('KES 2,000,000', false)
            ->assertSee('Enquire', false);
    }

    public function test_non_subscriber_and_expired_subscriber_are_denied(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $expired = User::factory()->withRoles('buyer')->withInvestorPass(now()->subDay())->create();
        $property = Property::factory()->published()->listedIn($this->locality())->create();

        $this->actingAs($buyer)->get(route('investor.opportunities.index'))->assertForbidden();
        $this->actingAs($buyer)->get(route('investor.opportunities.show', $property))->assertForbidden();
        $this->actingAs($expired)->get(route('investor.opportunities.index'))->assertForbidden();
    }

    public function test_only_published_available_properties_appear(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $locality = $this->locality();

        Property::factory()->published()->listedIn($locality)->create(['title' => 'Visible listing']);
        Property::factory()->published()->unavailable()->listedIn($locality)->create(['title' => 'Sold out listing']);
        Property::factory()->listedIn($locality)->create(['title' => 'Draft listing']);

        $this->actingAs($subscriber)->get(route('investor.opportunities.index'))
            ->assertOk()
            ->assertSee('Visible listing', false)
            ->assertDontSee('Sold out listing', false)
            ->assertDontSee('Draft listing', false);

        $draft = Property::query()->where('title', 'Draft listing')->firstOrFail();
        $this->actingAs($subscriber)->get(route('investor.opportunities.show', $draft))->assertNotFound();
    }

    public function test_search_filter_and_pagination(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $locality = $this->locality();

        Property::factory()->published()->listedIn($locality)->count(13)->create([
            'price_amount' => 2_000_000,
            'acreage' => 1,
        ]);
        Property::factory()->published()->listedIn($locality)->create([
            'title' => 'Manda ridge parcel',
            'locality' => 'Manda',
            'price_amount' => 9_000_000,
            'acreage' => 1,
        ]);

        $this->actingAs($subscriber)->get(route('investor.opportunities.index', ['q' => 'Manda']))
            ->assertOk()
            ->assertSee('Manda ridge parcel', false);

        $this->actingAs($subscriber)->get(route('investor.opportunities.index', ['min_price' => 8_000_000]))
            ->assertOk()
            ->assertSee('Manda ridge parcel', false);

        $response = $this->actingAs($subscriber)->get(route('investor.opportunities.index'));
        $response->assertOk();
        $this->assertSame(12, substr_count($response->getContent(), 'View Details'));
    }

    public function test_locality_and_county_intelligence_grouping_and_median_suppression(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $desk = app(PublicationDesk::class);
        $county = County::query()->create(['name' => 'Lamu', 'slug' => 'lamu', 'is_active' => true]);
        $shela = Locality::query()->create([
            'county_id' => $county->id,
            'name' => 'Shela',
            'slug' => 'shela',
            'kind' => 'place',
            'is_active' => true,
        ]);
        $manda = Locality::query()->create([
            'county_id' => $county->id,
            'name' => 'Manda',
            'slug' => 'manda',
            'kind' => 'place',
            'is_active' => true,
        ]);

        $property = Property::factory()->published()->listedIn($shela)->create([
            'title' => 'Shela intelligence target',
            'price_amount' => 2_000_000,
            'acreage' => 1,
        ]);
        Property::factory()->published()->listedIn($shela)->create([
            'price_amount' => 4_000_000,
            'acreage' => 1,
        ]);

        $localProject = DevelopmentProject::query()->create([
            'reference' => $desk->reference('DP', DevelopmentProject::class),
            'slug' => $desk->slug('Shela jetty', DevelopmentProject::class),
            'name' => 'Shela jetty',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Shela waterfront',
            'summary' => 'Local project summary.',
            'research_notes' => 'Secret local note',
            'source_name' => 'County notice',
            'county_id' => $county->id,
            'locality_id' => $shela->id,
            'publication_status' => 'review',
            'created_by' => $editor->id,
        ]);
        $this->actingAs($editor)->post(route('staff.projects.publish', $localProject))->assertRedirect();

        $countyProject = DevelopmentProject::query()->create([
            'reference' => $desk->reference('DP', DevelopmentProject::class),
            'slug' => $desk->slug('County road corridor', DevelopmentProject::class),
            'name' => 'County road corridor',
            'category' => 'road',
            'project_status' => 'proposed',
            'coverage' => 'Lamu County',
            'summary' => 'County-wide summary.',
            'source_name' => 'National gazette',
            'county_id' => $county->id,
            'locality_id' => $manda->id,
            'publication_status' => 'review',
            'created_by' => $editor->id,
        ]);
        $this->actingAs($editor)->post(route('staff.projects.publish', $countyProject))->assertRedirect();

        $this->actingAs($subscriber)->get(route('investor.opportunities.show', $property))
            ->assertOk()
            ->assertSee('Shela jetty', false)
            ->assertSee('County road corridor', false)
            ->assertSee('County-level infrastructure context', false)
            ->assertSee('County context', false)
            ->assertSee('Insufficient observations for a reliable locality median', false)
            ->assertDontSee('Secret local note', false);
    }

    public function test_unreleased_intelligence_and_premium_notes_stay_off_public_and_guest_views(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $locality = $this->locality();
        $property = Property::factory()->published()->listedIn($locality)->create(['title' => 'Planning target']);

        $this->actingAs($researcher)->post(route('staff.planning.store'), [
            'title' => 'Shela zoning draft',
            'area' => 'Shela',
            'locality_id' => $locality->id,
            'county_id' => $locality->county_id,
            'instrument_status' => 'in_force',
            'source_document' => 'Draft extract',
            'restrictions' => 'Premium restriction text',
        ])->assertRedirect();
        $draftPlan = PlanningRecord::query()->firstOrFail();

        $this->actingAs($subscriber)->get(route('investor.opportunities.show', $property))
            ->assertOk()
            ->assertDontSee('Premium restriction text', false);

        $this->actingAs($researcher)->post(route('staff.planning.submit', $draftPlan))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.planning.publish', $draftPlan))->assertRedirect();

        $this->actingAs($subscriber)->get(route('investor.opportunities.show', $property))
            ->assertOk()
            ->assertSee('Premium restriction text', false);

        auth()->logout();

        $this->get(route('intelligence.planning.show', $draftPlan->fresh()))
            ->assertOk()
            ->assertDontSee('Premium restriction text', false);
    }

    public function test_released_imagery_and_reports_appear_for_matching_locality(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $locality = $this->locality();
        $property = Property::factory()->published()->listedIn($locality)->create();

        $before = ImageryScene::query()->create([
            'reference' => 'IS-B',
            'provider' => 'Sentinel',
            'acquired_on' => now()->subYears(2),
            'resolution' => '10 m',
            'coverage' => 'Shela coast',
            'locality_id' => $locality->id,
            'county_id' => $locality->county_id,
            'redistribution_allowed' => true,
            'license_name' => 'Test licence',
            'publication_status' => 'published',
            'published_at' => now()->subDay(),
            'created_by' => $researcher->id,
        ]);
        $after = ImageryScene::query()->create([
            'reference' => 'IS-A',
            'provider' => 'Sentinel',
            'acquired_on' => now()->subYear(),
            'resolution' => '10 m',
            'coverage' => 'Shela coast',
            'locality_id' => $locality->id,
            'county_id' => $locality->county_id,
            'redistribution_allowed' => true,
            'license_name' => 'Test licence',
            'publication_status' => 'published',
            'published_at' => now()->subDay(),
            'created_by' => $researcher->id,
        ]);
        $comparison = ImageryComparison::query()->create([
            'reference' => 'IC-1',
            'slug' => 'shela-change',
            'area_label' => 'Shela shoreline change',
            'before_scene_id' => $before->id,
            'after_scene_id' => $after->id,
            'observed_change' => 'New access track visible.',
            'publication_status' => 'published',
            'published_at' => now()->subDay(),
            'created_by' => $researcher->id,
        ]);

        $this->actingAs($researcher)->post(route('staff.reports.store'), [
            'title' => 'Shela market note',
            'summary' => 'Public research summary.',
            'body' => 'Confidential body text.',
            'citation_label' => 'Desk file',
            'locality_ids' => [$locality->id],
        ])->assertRedirect();
        $report = ResearchReport::query()->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.reports.submit', $report))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.reports.publish', $report))->assertRedirect();

        $this->actingAs($subscriber)->get(route('investor.opportunities.show', $property))
            ->assertOk()
            ->assertSee('Shela shoreline change', false)
            ->assertSee('New access track visible.', false)
            ->assertSee('Shela market note', false)
            ->assertSee('Public research summary.', false)
            ->assertDontSee('Confidential body text', false);
    }

    public function test_public_land_catalog_remains_available_without_investor_pass(): void
    {
        $property = Property::factory()->published()->listedIn($this->locality())->create(['title' => 'Public catalog listing']);

        $this->get(route('land.index'))->assertOk()->assertSee('Public catalog listing', false);
        $this->get(route('land.enquiries.create', $property))->assertRedirect(route('login'));
    }

    private function locality(string $slug = 'shela'): Locality
    {
        $county = County::query()->firstOrCreate(
            ['slug' => 'lamu'],
            ['name' => 'Lamu', 'is_active' => true],
        );

        return Locality::query()->firstOrCreate(
            ['slug' => $slug, 'county_id' => $county->id],
            ['name' => ucfirst($slug), 'kind' => 'place', 'is_active' => true],
        );
    }
}
