<?php

namespace Tests\Feature\Intelligence;

use App\Enums\PublicationStatus;
use App\Models\DevelopmentProject;
use App\Models\User;
use App\Services\Intelligence\PublicationDesk;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Foundation\FoundationTestCase;

class InvestorPublicationReadinessTest extends FoundationTestCase
{
    public function test_project_submission_requires_verification_date_and_evidence(): void
    {
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();

        $this->actingAs($researcher)->post(route('staff.projects.store'), [
            'name' => 'Incomplete record',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu',
            'summary' => 'Summary.',
            'source_name' => 'County notice',
        ])->assertRedirect();

        $project = DevelopmentProject::query()->where('name', 'Incomplete record')->firstOrFail();

        $this->actingAs($researcher)->post(route('staff.projects.submit', $project))->assertStatus(422);

        $this->actingAs($researcher)->put(route('staff.projects.update', $project), [
            'name' => 'Incomplete record',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu',
            'summary' => 'Summary.',
            'source_name' => 'County notice',
            'verified_on' => now()->toDateString(),
        ])->assertRedirect();

        $this->actingAs($researcher)->post(route('staff.projects.submit', $project))->assertStatus(422);

        $this->actingAs($researcher)->post(route('staff.projects.evidence', $project), [
            'evidence' => UploadedFile::fake()->create('notice.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $this->actingAs($researcher)->post(route('staff.projects.submit', $project))->assertRedirect();
        $this->assertSame(PublicationStatus::Review, $project->fresh()->publication_status);
    }

    public function test_editor_withdraw_closes_public_and_premium_access(): void
    {
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($researcher)->post(route('staff.projects.store'), [
            'name' => 'Withdrawn port record',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu',
            'summary' => 'Public summary.',
            'source_name' => 'County notice',
            'research_notes' => 'Premium detail.',
            'verified_on' => now()->toDateString(),
        ])->assertRedirect();

        $project = DevelopmentProject::query()->where('name', 'Withdrawn port record')->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.projects.evidence', $project), [
            'evidence' => UploadedFile::fake()->create('notice.pdf', 20, 'application/pdf'),
        ])->assertRedirect();
        $this->actingAs($researcher)->post(route('staff.projects.submit', $project))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.projects.publish', $project))->assertRedirect();

        $this->actingAs($subscriber)->get(route('investor.projects.show', $project))->assertOk()->assertSee('Premium detail', false);

        $this->actingAs($editor)->post(route('staff.projects.withdraw', $project))->assertRedirect();
        $project->refresh();
        $this->assertSame(PublicationStatus::Withdrawn, $project->publication_status);
        $this->assertTrue($project->audits()->where('action', 'withdrawn')->exists());

        $this->get(route('intelligence.projects.show', $project))->assertNotFound();
        $this->actingAs($subscriber)->get(route('investor.projects.show', $project))->assertNotFound();
    }

    public function test_researcher_cannot_withdraw_published_research(): void
    {
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $desk = app(PublicationDesk::class);

        $project = DevelopmentProject::query()->create([
            'reference' => $desk->reference('DP', DevelopmentProject::class),
            'slug' => $desk->slug('Live record', DevelopmentProject::class),
            'name' => 'Live record',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu',
            'summary' => 'Summary.',
            'source_name' => 'County notice',
            'verified_on' => now()->toDateString(),
            'publication_status' => PublicationStatus::Review,
            'created_by' => $editor->id,
        ]);

        $this->actingAs($editor)->post(route('staff.projects.publish', $project))->assertRedirect();
        $this->actingAs($researcher)->post(route('staff.projects.withdraw', $project->fresh()))->assertForbidden();
    }

    public function test_intelligence_inventory_command_reports_environment(): void
    {
        $this->artisan('intelligence:inventory')
            ->assertSuccessful()
            ->expectsOutputToContain('Environment:')
            ->expectsOutputToContain('Infrastructure (development projects)');
    }
}
