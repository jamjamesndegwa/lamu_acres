<?php

namespace Tests\Feature\Subscription;

use App\Enums\AssignmentStatus;
use App\Enums\PublicationStatus;
use App\Enums\ResearchCommitment;
use App\Models\County;
use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\ImageryScene;
use App\Models\Locality;
use App\Models\PublicationDelivery;
use App\Models\ResearchAssignment;
use App\Models\ResearchReport;
use App\Models\SubscriberWatch;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Intelligence\PublicationDesk;
use App\Services\Subscription\Memberships;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schedule;
use Tests\Feature\Foundation\FoundationTestCase;

class PublicationCommitmentTest extends FoundationTestCase
{
    public function test_the_calendar_marks_overdue_work_and_does_not_invent_imagery(): void
    {
        $commands = collect(Schedule::events())->map(fn ($event) => $event->command)->implode(' ');
        $this->assertStringContainsString('research:commitments', $commands);
        $this->assertStringContainsString('mpesa:reconcile', $commands);

        Carbon::setTestNow('2026-04-05');
        $this->artisan('research:commitments')->assertSuccessful();

        $this->assertSame(0, ImageryComparison::query()->count());
        $this->assertSame(0, DevelopmentProject::query()->count());

        $imagery = ResearchAssignment::query()->where('commitment', ResearchCommitment::QuarterlyImagery)->whereDate('period_end', '2026-03-31')->firstOrFail();
        $this->assertSame(AssignmentStatus::Unavailable, $imagery->status);
        $this->assertStringContainsString('No usable published imagery', (string) $imagery->note);

        $weekly = ResearchAssignment::query()->where('commitment', ResearchCommitment::WeeklyInfrastructure)->whereDate('due_on', '2026-04-05')->firstOrFail();
        $this->assertSame(AssignmentStatus::Due, $weekly->status);

        $overdue = ResearchAssignment::query()->where('commitment', ResearchCommitment::WeeklyInfrastructure)->whereDate('period_end', '<', '2026-04-05')->firstOrFail();
        $this->assertSame(AssignmentStatus::Overdue, $overdue->status);

        $editor = User::factory()->withRoles('staff')->withPermissions('staff.intelligence', 'intelligence.publish')->create();
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $outsider = User::factory()->withRoles('buyer')->create();

        $draft = $this->report('Draft bulletin', PublicationStatus::Draft, 'SECRET-UNPUBLISHED-BODY');
        $this->actingAs($editor)->post(route('staff.commitments.report', $weekly), ['report_id' => $draft->id])->assertSessionHasErrors('report');
        $this->assertSame(0, PublicationDelivery::query()->count());

        $published = $this->report('April bulletin', PublicationStatus::Published, 'Published bulletin text');
        $this->actingAs($editor)->post(route('staff.commitments.report', $weekly), ['report_id' => $published->id])->assertRedirect();

        $this->assertSame(AssignmentStatus::Published, $weekly->fresh()->status);
        $this->assertTrue($subscriber->fresh()->notifications()->where('data->message', 'April bulletin')->exists());
        $this->assertFalse($outsider->fresh()->notifications()->exists());
        $this->assertFalse($subscriber->notifications()->where('data->message', 'like', '%SECRET-UNPUBLISHED-BODY%')->exists());

        $this->actingAs($subscriber)->get(route('investor.archive.index'))->assertOk()->assertSee('April bulletin')->assertDontSee('SECRET-UNPUBLISHED-BODY');
    }

    public function test_development_alerts_are_sent_only_after_publication(): void
    {
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $quiet = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $locality = $this->locality();

        $project = DevelopmentProject::query()->create([
            'reference' => 'DP-ALERT001',
            'slug' => 'recorded-port-works',
            'name' => 'Recorded port works',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu',
            'locality_id' => $locality->id,
            'summary' => 'A published summary.',
            'research_notes' => 'SECRET-RESEARCH-NOTE',
            'source_name' => 'County notice',
            'publication_status' => 'draft',
            'created_by' => $editor->id,
        ]);

        SubscriberWatch::query()->create([
            'user_id' => $subscriber->id,
            'locality_id' => $locality->id,
        ]);

        $this->actingAs($subscriber)->post(route('investor.watchlists.store'), [
            'development_project_id' => $project->id,
        ])->assertStatus(422);

        $desk = app(PublicationDesk::class);
        $desk->submit($project, $editor);
        $this->assertSame(0, $subscriber->notifications()->count());

        $desk->publish($project->fresh(), $editor);

        $notification = $subscriber->fresh()->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('Recorded port works', $notification->data['message']);
        $this->assertStringNotContainsString('SECRET-RESEARCH-NOTE', json_encode($notification->data));
        $this->assertSame(0, $quiet->notifications()->count());
    }

    public function test_a_service_credit_requires_authorization_and_extends_the_existing_account(): void
    {
        $finance = User::factory()->withRoles('staff')->withPermissions('staff.finance')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $memberships = app(Memberships::class);
        $subscription = $memberships->request($buyer);
        $memberships->activate($finance, $subscription);
        $ends = $subscription->fresh()->ends_at->copy();

        $this->actingAs($buyer)->post(route('staff.memberships.credit', $subscription), [
            'days' => 7,
            'reason' => 'Missed bulletin',
        ])->assertForbidden();

        $this->actingAs($finance)->post(route('staff.memberships.credit', $subscription), [
            'days' => 7,
            'reason' => 'Missed bulletin',
        ])->assertRedirect();

        $this->assertTrue($subscription->fresh()->ends_at->equalTo($ends->addDays(7)));
        $this->assertTrue($buyer->fresh()->hasActiveInvestorPass());
        $this->assertSame(1, Subscription::query()->where('user_id', $buyer->id)->count());
        $this->assertDatabaseHas('service_credits', [
            'subscription_id' => $subscription->id,
            'actor_id' => $finance->id,
            'days' => 7,
        ]);
    }

    private function report(string $title, PublicationStatus $status, string $body): ResearchReport
    {
        return ResearchReport::query()->create([
            'reference' => 'RR-'.strtoupper(substr(md5($title), 0, 8)),
            'slug' => str($title)->slug().'-'.str()->lower(str()->random(4)),
            'title' => $title,
            'summary' => 'Public summary',
            'body' => $body,
            'publication_status' => $status,
            'published_at' => $status === PublicationStatus::Published ? now() : null,
        ]);
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
