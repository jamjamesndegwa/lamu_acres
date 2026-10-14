<?php

namespace Tests\Feature\Intelligence;

use App\Enums\PublicationStatus;
use App\Models\DevelopmentMilestone;
use App\Models\DevelopmentProject;
use App\Models\User;
use Tests\Feature\Foundation\FoundationTestCase;

class DevelopmentPublicationTest extends FoundationTestCase
{
    public function test_publication_hides_drafts_and_premium_detail_until_an_editor_approves(): void
    {
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($researcher)->post(route('staff.projects.store'), [
            'name' => 'Recorded port works',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu County coastline',
            'summary' => 'A public summary of a sourced port record.',
            'source_name' => 'County notice',
            'research_notes' => 'Unconfirmed spur note',
        ])->assertRedirect();

        $project = DevelopmentProject::query()->where('name', 'Recorded port works')->firstOrFail();

        $this->assertSame(PublicationStatus::Draft, $project->publication_status);
        $this->assertNull($project->latitude);
        $this->assertNull($project->longitude);
        $this->assertSame(0, DevelopmentMilestone::query()->count());
        $this->get(route('intelligence.projects.show', $project))->assertNotFound();
        $this->actingAs($subscriber)->get(route('investor.projects.show', $project))->assertNotFound();

        $this->actingAs($researcher)->put(route('staff.projects.update', $project), [
            'name' => 'Recorded port works',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu County coastline',
            'summary' => 'A public summary of a sourced port record.',
            'source_name' => 'County notice',
            'research_notes' => 'Unconfirmed spur note',
            'latitude' => -2.2694,
            'longitude' => 40.9012,
            'boundary_wkt' => 'POLYGON ((40.90 -2.27, 40.91 -2.27, 40.91 -2.26, 40.90 -2.27))',
        ])->assertRedirect();

        $this->actingAs($researcher)->post(route('staff.projects.submit', $project))->assertRedirect();
        $this->actingAs($researcher)->post(route('staff.projects.publish', $project))->assertForbidden();
        $this->actingAs($editor)->post(route('staff.projects.publish', $project))->assertRedirect();

        $project->refresh();
        $this->assertTrue($project->isReleased());
        $this->assertTrue($project->audits()->where('action', 'published')->exists());

        auth()->logout();

        $this->get(route('intelligence.projects.show', $project))
            ->assertOk()
            ->assertSee('Recorded port works')
            ->assertSee('Lamu County coastline')
            ->assertSee('Proposed')
            ->assertSee('Published')
            ->assertDontSee('Unconfirmed spur note')
            ->assertDontSee('-2.2694')
            ->assertDontSee('POLYGON')
            ->assertDontSee('Change history');

        $this->actingAs($buyer)
            ->get(route('intelligence.projects.show', $project))
            ->assertOk()
            ->assertDontSee('Unconfirmed spur note');

        $this->actingAs($subscriber)
            ->get(route('intelligence.projects.show', $project))
            ->assertOk()
            ->assertSee('Unconfirmed spur note')
            ->assertSee('-2.2694')
            ->assertSee('POLYGON ((40.90 -2.27, 40.91 -2.27, 40.91 -2.26, 40.90 -2.27))')
            ->assertSee('Change history')
            ->assertSee('published')
            ->assertSee('No milestones have been recorded.');
    }
}
