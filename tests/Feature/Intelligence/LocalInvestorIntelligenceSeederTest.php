<?php

namespace Tests\Feature\Intelligence;

use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\PlanningRecord;
use App\Models\Property;
use App\Models\ResearchReport;
use App\Models\SubscriberWatch;
use App\Models\User;
use Database\Seeders\GeographySeeder;
use Database\Seeders\LocalInvestorIntelligenceSeeder;
use Database\Seeders\SystemDefaultsSeeder;
use Tests\Feature\Foundation\FoundationTestCase;

class LocalInvestorIntelligenceSeederTest extends FoundationTestCase
{
    public function test_local_investor_intelligence_seeder_populates_subscriber_content(): void
    {
        $this->seed([GeographySeeder::class, SystemDefaultsSeeder::class]);
        User::factory()->withRoles('buyer')->withInvestorPass()->create([
            'email' => 'investor@lamuacres.test',
            'name' => 'Investor Account',
        ]);
        User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create([
            'email' => 'research@lamuacres.test',
            'name' => 'Research Officer',
        ]);
        User::factory()->withRoles('staff')->withPermissions('staff.intelligence', 'intelligence.publish')->create([
            'email' => 'editor@lamuacres.test',
            'name' => 'Research Editor',
        ]);

        $this->seed(LocalInvestorIntelligenceSeeder::class);

        $this->assertSame(1, DevelopmentProject::query()->where('slug', 'demo-lapsset-lamu-corridor')->released()->count());
        $this->assertGreaterThanOrEqual(2, DevelopmentProject::query()->released()->count());
        $this->assertGreaterThanOrEqual(1, PlanningRecord::query()->released()->count());
        $this->assertGreaterThanOrEqual(2, ResearchReport::query()->released()->count());
        $this->assertGreaterThanOrEqual(1, ImageryComparison::query()->released()->count());
        $this->assertGreaterThanOrEqual(4, Property::query()->published()->count());

        $investor = User::query()->where('email', 'investor@lamuacres.test')->firstOrFail();
        $this->assertTrue($investor->hasActiveInvestorPass());
        $this->assertGreaterThanOrEqual(1, SubscriberWatch::query()->where('user_id', $investor->id)->count());

        $this->actingAs($investor)->get(route('investor.dashboard'))
            ->assertOk()
            ->assertSee('LAPSSET Lamu corridor (demo)', false);

        $this->actingAs($investor)->get(route('investor.opportunities.index'))
            ->assertOk()
            ->assertSee('Demo Shela freehold', false);

        $this->actingAs($investor)->get(route('investor.projects.index'))
            ->assertOk()
            ->assertSee('LAPSSET Lamu corridor (demo)', false);

        $this->actingAs($investor)->get(route('investor.reports.index'))
            ->assertOk()
            ->assertSee('Shela coastal access outlook (demo)', false);

        $this->actingAs($investor)->get(route('investor.watchlists.index'))
            ->assertOk()
            ->assertSee('Shela', false);

        $this->actingAs($investor)->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('LAPSSET Lamu corridor (demo)', false);
    }

    public function test_staff_can_open_intelligence_create_pages(): void
    {
        User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create([
            'email' => 'research@lamuacres.test',
            'name' => 'Research Officer',
        ]);
        $researcher = User::query()->where('email', 'research@lamuacres.test')->firstOrFail();

        $this->actingAs($researcher)->get(route('staff.projects.create'))->assertOk();
        $this->actingAs($researcher)->get(route('staff.reports.create'))->assertOk();
        $this->actingAs($researcher)->get(route('staff.planning.create'))->assertOk();
        $this->actingAs($researcher)->get(route('staff.imagery.scenes.create'))->assertOk();
    }
}
