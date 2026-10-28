<?php

namespace Tests\Feature\Intelligence;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Enums\PublicationStatus;
use App\Models\County;
use App\Models\DevelopmentProject;
use App\Models\ImageryScene;
use App\Models\IntelligenceFile;
use App\Models\Locality;
use App\Models\MessageDelivery;
use App\Models\MessageTemplate;
use App\Models\ResearchReport;
use App\Models\SubscriberWatch;
use App\Models\User;
use App\Services\Communications\MessageDesk;
use App\Services\Research\WatchAlertContext;
use Tests\Feature\Foundation\FoundationTestCase;

class InvestorLaunchCertificationTest extends FoundationTestCase
{
    public function test_research_watch_alert_template_includes_branded_fields(): void
    {
        $template = MessageTemplate::query()->where('event', MessageEvent::ResearchWatchAlert)->firstOrFail();
        $this->assertStringContainsString('{watch}', $template->body);
        $this->assertStringContainsString('{status}', $template->body);
        $this->assertStringContainsString('Investor workspace', $template->body);

        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create(['notify_by_email' => true]);
        $locality = $this->locality();
        $report = ResearchReport::query()->create([
            'reference' => 'RR-TEMPLATE',
            'slug' => 'template-report',
            'title' => 'Coastal outlook',
            'summary' => 'Public summary for the email body.',
            'body' => 'Confidential full report body.',
            'publication_status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
        $report->localities()->attach($locality);
        SubscriberWatch::query()->create(['user_id' => $subscriber->id, 'locality_id' => $locality->id]);

        $context = app(WatchAlertContext::class)->forEmail($subscriber, $report);
        app(MessageDesk::class)->send($subscriber, MessageEvent::ResearchWatchAlert, $context, 'watch:test:1', null, [MessageChannel::Email]);

        $delivery = MessageDelivery::query()
            ->where('user_id', $subscriber->id)
            ->where('event', MessageEvent::ResearchWatchAlert)
            ->firstOrFail();

        $this->assertStringContainsString('Lamu Acres', $delivery->subject);
        $this->assertStringContainsString('Coastal outlook', $delivery->body);
        $this->assertStringContainsString('Location · '.$locality->name, $delivery->body);
        $this->assertStringContainsString('Research report', $delivery->body);
        $this->assertStringContainsString('Public summary for the email body.', $delivery->body);
        $this->assertStringContainsString(route('investor.reports.show', $report), $delivery->body);
        $this->assertStringNotContainsString('Confidential full report body', $delivery->body);
    }

    public function test_report_broadcast_skips_watchers_to_avoid_duplicate_emails(): void
    {
        $watcher = User::factory()->withRoles('buyer')->withInvestorPass()->create(['notify_by_email' => true]);
        $general = User::factory()->withRoles('buyer')->withInvestorPass()->create(['notify_by_email' => true]);
        $locality = $this->locality();
        SubscriberWatch::query()->create(['user_id' => $watcher->id, 'locality_id' => $locality->id]);

        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();

        $this->actingAs($researcher)->post(route('staff.reports.store'), [
            'title' => 'Shela premium note',
            'summary' => 'Summary for subscribers.',
            'body' => 'Confidential analysis.',
            'citation_label' => 'Desk file',
            'locality_ids' => [$locality->id],
        ])->assertRedirect();

        $report = ResearchReport::query()->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.reports.submit', $report))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.reports.publish', $report))->assertRedirect();

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $watcher->id)
                ->where('event', MessageEvent::ResearchWatchAlert)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
        $this->assertFalse(
            MessageDelivery::query()
                ->where('user_id', $watcher->id)
                ->where('event', MessageEvent::ReportPublished)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $general->id)
                ->where('event', MessageEvent::ReportPublished)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
    }

    public function test_expired_subscriber_can_still_open_renewal_route(): void
    {
        $expired = User::factory()->withRoles('buyer')->withInvestorPass(now()->subDay())->create();

        $this->actingAs($expired)->get(route('investor.dashboard'))->assertForbidden();
        $this->actingAs($expired)->get(route('buyer.subscription.show'))->assertOk();
        $this->actingAs($expired)->get(route('investor.subscription.show'))->assertForbidden();
    }

    public function test_restricted_imagery_file_download_stays_licence_gated(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $scene = ImageryScene::query()->create([
            'reference' => 'IM-LIC',
            'provider' => 'Licensed archive',
            'acquired_on' => now()->subYear(),
            'resolution' => '10 m',
            'coverage' => 'Shela',
            'license_name' => 'Restricted licence',
            'redistribution_allowed' => false,
            'publication_status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
            'created_by' => $subscriber->id,
        ]);
        $file = IntelligenceFile::query()->create([
            'subject_type' => $scene->getMorphClass(),
            'subject_id' => $scene->id,
            'kind' => 'scene',
            'disk' => 'local',
            'path' => 'intelligence/imagery_scenes/'.$scene->id.'/scene.jpg',
            'original_name' => 'scene.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 100,
            'uploaded_by' => $subscriber->id,
        ]);

        $this->get(route('intelligence.files.show', $file))->assertForbidden();
        $this->actingAs($subscriber)->get(route('intelligence.files.show', $file))->assertForbidden();
    }

    public function test_investor_dashboard_lists_only_released_intelligence(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        DevelopmentProject::query()->create([
            'reference' => 'DP-LIVE',
            'slug' => 'live-project',
            'name' => 'Live port record',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu',
            'summary' => 'Released summary.',
            'source_name' => 'County notice',
            'publication_status' => PublicationStatus::Published,
            'published_at' => now()->subDay(),
            'created_by' => $subscriber->id,
        ]);
        DevelopmentProject::query()->create([
            'reference' => 'DP-DRAFT',
            'slug' => 'draft-project',
            'name' => 'Draft secret project',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu',
            'summary' => 'Draft summary.',
            'source_name' => 'County notice',
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $subscriber->id,
        ]);

        $this->actingAs($subscriber)->get(route('investor.dashboard'))
            ->assertOk()
            ->assertSee('Live port record', false)
            ->assertDontSee('Draft secret project', false);
    }

    private function locality(): Locality
    {
        $county = County::query()->firstOrCreate(['slug' => 'lamu'], ['name' => 'Lamu', 'is_active' => true]);

        return Locality::query()->firstOrCreate(
            ['slug' => 'shela', 'county_id' => $county->id],
            ['name' => 'Shela', 'kind' => 'place', 'is_active' => true],
        );
    }
}
