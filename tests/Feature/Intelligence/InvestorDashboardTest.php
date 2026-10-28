<?php

namespace Tests\Feature\Intelligence;

use App\Models\County;
use App\Models\DevelopmentProject;
use App\Models\Locality;
use App\Models\PlanningRecord;
use App\Models\Property;
use App\Models\ResearchReport;
use App\Models\SubscriberWatch;
use App\Models\User;
use App\Services\Intelligence\PriceObservatory;
use App\Services\Intelligence\PublicationDesk;
use Tests\Feature\Foundation\FoundationTestCase;

class InvestorDashboardTest extends FoundationTestCase
{
    public function test_active_subscriber_sees_dashboard_sections_and_counts(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $desk = app(PublicationDesk::class);

        $project = DevelopmentProject::query()->create([
            'reference' => $desk->reference('DP', DevelopmentProject::class),
            'slug' => $desk->slug('Harbor spur', DevelopmentProject::class),
            'name' => 'Harbor spur',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu port area',
            'summary' => 'Public summary.',
            'research_notes' => 'Premium note',
            'source_name' => 'County notice',
            'source_published_on' => now()->subMonth(),
            'verified_on' => now()->subWeek(),
            'publication_status' => 'review',
            'created_by' => $researcher->id,
        ]);
        $this->actingAs($editor)->post(route('staff.projects.publish', $project))->assertRedirect();

        SubscriberWatch::query()->create([
            'user_id' => $subscriber->id,
            'development_project_id' => $project->fresh()->id,
        ]);

        $this->actingAs($subscriber)->get(route('investor.dashboard'))
            ->assertOk()
            ->assertSee('Published reports', false)
            ->assertSee('Infrastructure updates', false)
            ->assertSee('Harbor spur', false)
            ->assertSee('My watchlist', false)
            ->assertSee('My Subscription', false);

        $this->assertSame(1, DevelopmentProject::query()->released()->count());
    }

    public function test_unsubscribed_buyer_cannot_access_investor_dashboard(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->get(route('investor.dashboard'))->assertForbidden();
    }

    public function test_price_median_is_suppressed_for_small_samples(): void
    {
        $locality = $this->locality();

        Property::factory()->published()->listedIn($locality)->create([
            'price_amount' => 1_000_000,
            'acreage' => 1,
        ]);
        Property::factory()->published()->listedIn($locality)->create([
            'price_amount' => 2_000_000,
            'acreage' => 1,
        ]);

        $row = app(PriceObservatory::class)->askingByLocality()->first();
        $this->assertSame(2, $row['sample']);
        $this->assertNull($row['median']);

        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($subscriber)->get(route('investor.prices'))
            ->assertOk()
            ->assertSee('Insufficient observations for a reliable locality median', false);
    }

    public function test_planning_due_diligence_notice_appears_for_subscriber(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();

        $this->actingAs($researcher)->post(route('staff.planning.store'), [
            'title' => 'Shela land-use note',
            'area' => 'Shela',
            'instrument_status' => 'in_force',
            'source_document' => 'County plan extract',
            'restrictions' => 'No industrial use was stated in the extract.',
            'permitted_uses' => 'Residential plots were listed.',
        ])->assertRedirect();

        $record = PlanningRecord::query()->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.planning.submit', $record))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.planning.publish', $record))->assertRedirect();

        $this->actingAs($subscriber)->get(route('investor.planning.show', $record))
            ->assertOk()
            ->assertSee('Planning information is provided for research purposes', false);
    }

    private function locality(): Locality
    {
        $county = County::query()->create(['name' => 'Lamu', 'slug' => 'lamu', 'is_active' => true]);

        return Locality::query()->create([
            'county_id' => $county->id,
            'name' => 'Shela',
            'slug' => 'shela',
            'kind' => 'place',
            'is_active' => true,
        ]);
    }
}
