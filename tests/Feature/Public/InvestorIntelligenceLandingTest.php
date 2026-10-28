<?php

namespace Tests\Feature\Public;

use App\Enums\PublicationStatus;
use App\Models\DevelopmentProject;
use App\Models\ResearchReport;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Intelligence\PublicationDesk;
use App\Services\Subscription\Memberships;
use Tests\Feature\Foundation\FoundationTestCase;

class InvestorIntelligenceLandingTest extends FoundationTestCase
{
    public function test_guest_can_view_investor_intelligence_landing_page(): void
    {
        $this->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('Invest in Land with Better Information.', false)
            ->assertSee('Investor Intelligence — Lamu Acres', false);
    }

    public function test_public_top_navigation_links_to_dedicated_landing_route(): void
    {
        $this->get(route('land.index'))
            ->assertOk()
            ->assertSee(route('investor-intelligence.index'), false)
            ->assertDontSee(route('home').'#intelligence', false);
    }

    public function test_landing_page_displays_benefit_and_comparison_sections(): void
    {
        $this->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('Research land before you commit', false)
            ->assertSee('What you can explore', false)
            ->assertSee('Public access', false)
            ->assertSee('Investor Pass subscription', false)
            ->assertSee('How Investor Pass works', false)
            ->assertSee('Frequently asked questions', false);
    }

    public function test_published_research_previews_appear_on_landing_page(): void
    {
        $project = $this->publishedProject('Shela jetty upgrade', 'Public jetty summary.');

        $this->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('Shela jetty upgrade', false)
            ->assertSee('Public jetty summary.', false)
            ->assertSee(route('intelligence.projects.show', $project), false);
    }

    public function test_draft_research_never_appears_on_landing_page(): void
    {
        DevelopmentProject::query()->create([
            'reference' => 'DP-DRAFT-ONLY',
            'slug' => 'draft-only-project',
            'name' => 'Draft-only harbour study',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu',
            'summary' => 'Draft public summary',
            'research_notes' => 'PREMIUM_DRAFT_SECRET_NOTES',
            'source_name' => 'Internal',
            'publication_status' => PublicationStatus::Draft,
            'created_by' => User::factory()->create()->id,
        ]);

        $this->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertDontSee('Draft-only harbour study', false)
            ->assertDontSee('PREMIUM_DRAFT_SECRET_NOTES', false);
    }

    public function test_premium_fields_are_not_exposed_to_guests_on_landing_page(): void
    {
        $report = ResearchReport::query()->create([
            'reference' => 'RR-PUBLIC-1',
            'slug' => 'public-report-preview',
            'title' => 'Coastal access briefing',
            'summary' => 'Safe public summary line.',
            'body' => 'PREMIUM_REPORT_BODY_SECRET',
            'publication_status' => PublicationStatus::Published,
            'published_at' => now(),
            'created_by' => User::factory()->create()->id,
        ]);

        $this->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('Coastal access briefing', false)
            ->assertSee('Safe public summary line.', false)
            ->assertDontSee('PREMIUM_REPORT_BODY_SECRET', false);
    }

    public function test_public_preview_links_resolve(): void
    {
        $project = $this->publishedProject('Manda causeway watch', 'Causeway summary.');

        $this->get(route('investor-intelligence.index'))
            ->assertOk();

        $this->get(route('intelligence.projects.show', $project))->assertOk();
    }

    public function test_pricing_section_reflects_configured_subscription_plan(): void
    {
        $plan = SubscriptionPlan::investorPass();
        $plan->update([
            'name' => 'Lamu Investor Pass Test',
            'amount_cents' => 15000,
            'currency' => 'USD',
            'interval' => 'year',
            'checkout_amount' => 12900,
            'checkout_currency' => 'KES',
            'checkout_policy' => 'Configured test policy.',
        ]);

        $this->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('Lamu Investor Pass Test', false)
            ->assertSee('USD 150.00 / year', false)
            ->assertSee('KES 12,900', false)
            ->assertSee('Configured test policy.', false);
    }

    public function test_landing_shows_truthful_message_when_kes_checkout_is_not_configured(): void
    {
        SubscriptionPlan::investorPass()->update([
            'checkout_amount' => null,
            'checkout_currency' => 'KES',
            'checkout_policy' => null,
        ]);

        $this->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('No payable KES amount has been set', false)
            ->assertSee('Online M-Pesa checkout is not available', false);
    }

    public function test_guest_subscription_call_to_action_points_to_registration(): void
    {
        $this->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('Get Investor Pass', false)
            ->assertSee(route('register'), false);
    }

    public function test_authenticated_buyer_without_pass_receives_subscription_call_to_action(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('Subscribe to Investor Pass', false)
            ->assertSee(route('buyer.subscription.show'), false);
    }

    public function test_active_subscriber_receives_investor_dashboard_call_to_action(): void
    {
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($subscriber)->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('Open Investor Dashboard', false)
            ->assertSee(route('investor.dashboard'), false);
    }

    public function test_expired_subscriber_receives_renewal_call_to_action_on_buyer_subscription_route(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $finance = User::factory()->withRoles('staff')->withPermissions('staff.finance')->create();
        $memberships = app(Memberships::class);
        $subscription = $memberships->request($buyer);
        $memberships->activate($finance, $subscription);
        $subscription->update(['ends_at' => now()->subDay()]);
        $memberships->expireIfDue($subscription->fresh());

        $this->actingAs($buyer)->get(route('investor-intelligence.index'))
            ->assertOk()
            ->assertSee('Renew Investor Pass', false)
            ->assertSee(route('buyer.subscription.show'), false);
    }

    public function test_broker_subscription_page_remains_available_for_brokers(): void
    {
        $broker = User::factory()->withRoles('broker')->create();

        $this->actingAs($broker)->get(route('broker.subscription.show'))->assertOk();
    }

    private function publishedProject(string $name, string $summary): DevelopmentProject
    {
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $desk = app(PublicationDesk::class);

        $project = DevelopmentProject::query()->create([
            'reference' => $desk->reference('DP', DevelopmentProject::class),
            'slug' => $desk->slug($name, DevelopmentProject::class),
            'name' => $name,
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu County',
            'summary' => $summary,
            'research_notes' => 'Subscriber-only milestone notes',
            'source_name' => 'County notice',
            'publication_status' => PublicationStatus::Review,
            'created_by' => $editor->id,
        ]);

        $this->actingAs($editor)->post(route('staff.projects.publish', $project))->assertRedirect();

        return $project->fresh();
    }
}
