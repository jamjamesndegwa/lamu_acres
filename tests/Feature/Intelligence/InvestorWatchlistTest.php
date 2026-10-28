<?php

namespace Tests\Feature\Intelligence;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Enums\PublicationStatus;
use App\Models\County;
use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\ImageryScene;
use App\Models\Locality;
use App\Models\MessageDelivery;
use App\Models\PlanningRecord;
use App\Models\PublicationDelivery;
use App\Models\ResearchReport;
use App\Models\SubscriberWatch;
use App\Models\User;
use App\Services\Intelligence\PublicationDesk;
use App\Services\Research\CommitmentDesk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class InvestorWatchlistTest extends FoundationTestCase
{
    public function test_subscriber_can_watch_and_unwatch_locality_and_project_without_duplicates(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $other = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $locality = $this->locality('shela');
        $project = $this->releasedProject($locality, 'Shela pier');

        $this->actingAs($subscriber)->post(route('investor.watchlists.store'), [
            'locality_id' => $locality->id,
        ])->assertRedirect()->assertSessionHas('status', 'Item added to your watchlist.');

        $this->actingAs($subscriber)->post(route('investor.watchlists.store'), [
            'locality_id' => $locality->id,
        ])->assertRedirect()->assertSessionHas('status', 'You are already watching this item.');

        $this->assertSame(1, SubscriberWatch::query()->where('user_id', $subscriber->id)->where('locality_id', $locality->id)->count());

        $this->actingAs($subscriber)->post(route('investor.watchlists.store'), [
            'development_project_id' => $project->id,
        ])->assertRedirect();

        $localityWatch = SubscriberWatch::query()->where('user_id', $subscriber->id)->where('locality_id', $locality->id)->firstOrFail();
        $projectWatch = SubscriberWatch::query()->where('user_id', $subscriber->id)->where('development_project_id', $project->id)->firstOrFail();

        $this->actingAs($subscriber)->delete(route('investor.watchlists.destroy', $localityWatch))
            ->assertRedirect()
            ->assertSessionHas('status', 'Item removed from your watchlist.');

        $this->actingAs($other)->delete(route('investor.watchlists.destroy', $projectWatch))->assertForbidden();
        $this->assertModelExists($projectWatch);
    }

    public function test_expired_subscriber_cannot_use_watch_actions(): void
    {
        $expired = User::factory()->withRoles('buyer')->withInvestorPass(now()->subDay())->create();
        $locality = $this->locality();

        $this->actingAs($expired)->post(route('investor.watchlists.store'), ['locality_id' => $locality->id])->assertForbidden();
        $this->actingAs($expired)->get(route('investor.watchlists.index'))->assertForbidden();
    }

    public function test_development_publication_alerts_matching_watchers_only_once(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create(['notify_by_email' => true]);
        $quiet = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $locality = $this->locality();
        $project = $this->draftProject($locality, 'Port spur');

        SubscriberWatch::query()->create(['user_id' => $subscriber->id, 'locality_id' => $locality->id]);
        SubscriberWatch::query()->create(['user_id' => $subscriber->id, 'development_project_id' => $project->id]);

        $this->publishProject($project);

        $this->assertSame(1, $subscriber->fresh()->notifications()->count());
        $this->assertSame(1, PublicationDelivery::query()->where('user_id', $subscriber->id)->count());
        $this->assertSame(0, $quiet->fresh()->notifications()->count());

        app(CommitmentDesk::class)->afterPublication($project->fresh());
        $this->assertSame(1, $subscriber->fresh()->notifications()->count());
        $this->assertSame(1, PublicationDelivery::query()->where('user_id', $subscriber->id)->count());
    }

    public function test_unrelated_locality_and_unreleased_content_do_not_alert(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $shela = $this->locality('shela');
        $manda = $this->locality('manda');
        SubscriberWatch::query()->create(['user_id' => $subscriber->id, 'locality_id' => $shela->id]);

        $countyProject = $this->draftProject($manda, 'Manda road');
        $countyProject->update(['locality_id' => null]);
        $this->publishProject($countyProject);

        $this->assertSame(0, $subscriber->fresh()->notifications()->count());

        $draft = $this->draftProject($shela, 'Shela draft');
        app(CommitmentDesk::class)->afterPublication($draft);
        $this->assertSame(0, $subscriber->fresh()->notifications()->count());
    }

    public function test_report_planning_and_imagery_publications_alert_locality_watchers(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create(['notify_by_email' => false]);
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $locality = $this->locality();
        SubscriberWatch::query()->create(['user_id' => $subscriber->id, 'locality_id' => $locality->id]);

        $this->actingAs($researcher)->post(route('staff.planning.store'), [
            'title' => 'Shela zoning note',
            'area' => 'Shela',
            'locality_id' => $locality->id,
            'county_id' => $locality->county_id,
            'instrument_status' => 'in_force',
            'source_document' => 'County extract',
            'restrictions' => 'Premium restriction line',
        ])->assertRedirect();
        $plan = PlanningRecord::query()->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.planning.submit', $plan))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.planning.publish', $plan))->assertRedirect();

        $this->actingAs($researcher)->post(route('staff.reports.store'), [
            'title' => 'Shela outlook',
            'summary' => 'Public summary only.',
            'body' => 'Confidential body',
            'citation_label' => 'Desk note',
            'locality_ids' => [$locality->id],
        ])->assertRedirect();
        $report = ResearchReport::query()->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.reports.submit', $report))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.reports.publish', $report))->assertRedirect();

        $this->assertGreaterThanOrEqual(2, $subscriber->fresh()->notifications()->count());
        $this->assertFalse($subscriber->notifications()->where('data', 'like', '%Confidential body%')->exists());
        $this->assertFalse($subscriber->notifications()->where('data', 'like', '%Premium restriction line%')->exists());

        $this->assertFalse(
            MessageDelivery::query()
                ->where('user_id', $subscriber->id)
                ->where('event', MessageEvent::ResearchWatchAlert)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
    }

    public function test_imagery_publication_alerts_locality_watchers(): void
    {
        Storage::fake('local');

        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $locality = $this->locality();
        SubscriberWatch::query()->create(['user_id' => $subscriber->id, 'locality_id' => $locality->id]);

        $before = $this->scene($researcher, $locality, '2022-01-01');
        $after = $this->scene($researcher, $locality, '2024-01-01');
        $this->actingAs($editor)->post(route('staff.imagery.scenes.publish', $before))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.imagery.scenes.publish', $after))->assertRedirect();

        $this->actingAs($researcher)->post(route('staff.imagery.comparisons.store'), [
            'area_label' => 'Shela shoreline imagery',
            'before_scene_id' => $before->id,
            'after_scene_id' => $after->id,
            'observed_change' => 'A new track is visible along the shoreline reserve.',
        ])->assertRedirect();

        $comparison = ImageryComparison::query()->latest('id')->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.imagery.comparisons.submit', $comparison))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.imagery.comparisons.publish', $comparison))->assertRedirect();

        $this->assertTrue($subscriber->fresh()->notifications()->where('data->message', 'Shela shoreline imagery')->exists());
    }

    public function test_optional_research_watch_email_respects_notify_by_email(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create(['notify_by_email' => true]);
        $locality = $this->locality();
        SubscriberWatch::query()->create(['user_id' => $subscriber->id, 'locality_id' => $locality->id]);
        $project = $this->draftProject($locality, 'Email test project');
        $this->publishProject($project);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $subscriber->id)
                ->where('event', MessageEvent::ResearchWatchAlert)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
    }

    public function test_archive_paginates_links_and_hides_withdrawn_content(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $locality = $this->locality();
        SubscriberWatch::query()->create(['user_id' => $subscriber->id, 'locality_id' => $locality->id]);
        $project = $this->draftProject($locality, 'Archive project');
        $this->publishProject($project);

        $this->actingAs($subscriber)->get(route('investor.archive.index'))
            ->assertOk()
            ->assertSee('Archive project', false)
            ->assertSee('Open', false);

        $project->update(['publication_status' => PublicationStatus::Withdrawn, 'published_at' => null]);

        $this->actingAs($subscriber)->get(route('investor.archive.index'))
            ->assertOk()
            ->assertSee('No longer available', false)
            ->assertDontSee('SECRET', false);

        $other = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $this->actingAs($other)->get(route('investor.archive.index'))->assertOk()->assertDontSee('Archive project', false);
    }

    public function test_mandatory_security_email_policy_is_unchanged_for_research_watch_event(): void
    {
        $this->assertFalse(MessageEvent::ResearchWatchAlert->isMandatory());
        $this->assertTrue(MessageEvent::PasswordResetRequested->isMandatory());
    }

    private function locality(string $slug = 'shela'): Locality
    {
        $county = County::query()->firstOrCreate(['slug' => 'lamu'], ['name' => 'Lamu', 'is_active' => true]);

        return Locality::query()->firstOrCreate(
            ['slug' => $slug, 'county_id' => $county->id],
            ['name' => ucfirst($slug), 'kind' => 'place', 'is_active' => true],
        );
    }

    private function draftProject(Locality $locality, string $name): DevelopmentProject
    {
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $desk = app(PublicationDesk::class);

        return DevelopmentProject::query()->create([
            'reference' => $desk->reference('DP', DevelopmentProject::class),
            'slug' => $desk->slug($name, DevelopmentProject::class),
            'name' => $name,
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => $locality->name,
            'locality_id' => $locality->id,
            'county_id' => $locality->county_id,
            'summary' => 'Published summary.',
            'research_notes' => 'SECRET',
            'source_name' => 'County notice',
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $editor->id,
        ]);
    }

    private function releasedProject(Locality $locality, string $name): DevelopmentProject
    {
        $project = $this->draftProject($locality, $name);
        $this->publishProject($project);

        return $project->fresh();
    }

    private function publishProject(DevelopmentProject $project): void
    {
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $desk = app(PublicationDesk::class);
        if ($project->publication_status !== PublicationStatus::Review) {
            $desk->submit($project, $editor);
        }
        $desk->publish($project->fresh(), $editor);
    }

    private function scene(User $researcher, Locality $locality, string $date): ImageryScene
    {
        $this->actingAs($researcher)->post(route('staff.imagery.scenes.store'), [
            'provider' => 'Licensed archive',
            'acquired_on' => $date,
            'resolution' => '10 m',
            'coverage' => $locality->name,
            'cloud_conditions' => 'Clear',
            'license_name' => 'Staff-held licence',
            'source_url' => 'https://example.com/scene-not-fetched',
            'locality_id' => $locality->id,
            'redistribution_allowed' => '0',
            'notes' => 'Held for review.',
            'image' => UploadedFile::fake()->image('scene.jpg'),
        ])->assertRedirect();

        $scene = ImageryScene::query()->latest('id')->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.imagery.scenes.submit', $scene))->assertRedirect();

        return $scene;
    }
}
