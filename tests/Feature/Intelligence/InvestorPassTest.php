<?php

namespace Tests\Feature\Intelligence;

use App\Enums\SubscriptionStatus;
use App\Models\DevelopmentProject;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Intelligence\PublicationDesk;
use Tests\Feature\Foundation\FoundationTestCase;

class InvestorPassTest extends FoundationTestCase
{
    public function test_a_pass_uses_the_existing_account_and_only_an_active_entitlement_opens_research(): void
    {
        $admin = User::factory()->withRoles('admin')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $broker = User::factory()->withRoles('broker')->create();
        $seller = User::factory()->withRoles('seller')->create();
        $finance = User::factory()->withRoles('staff')->withPermissions('staff.finance')->create();
        $accounts = User::query()->count();

        $this->assertSame(10000, SubscriptionPlan::investorPass()->amount_cents);
        $this->assertFalse(Role::query()->where('name', 'investor')->exists());

        $this->actingAs($admin)->put(route('admin.plans.update'), [
            'name' => 'Lamu Investor Pass',
            'amount' => '150.00',
            'currency' => 'USD',
            'interval' => 'year',
            'terms' => 'Annual access. Payment is not collected here.',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame(15000, SubscriptionPlan::investorPass()->fresh()->amount_cents);

        $this->actingAs($seller)->post(route('buyer.subscription.store'))->assertForbidden();
        $this->actingAs($buyer)->post(route('buyer.subscription.store'))->assertRedirect();
        $this->actingAs($broker)->post(route('broker.subscription.store'))->assertRedirect();

        $this->assertSame($accounts, User::query()->count());
        $this->assertFalse($buyer->fresh()->hasActiveInvestorPass());
        $this->actingAs($buyer)->get('/investor')->assertForbidden();

        $buyerSubscription = Subscription::query()->where('user_id', $buyer->id)->firstOrFail();
        $this->assertSame($buyer->id, $buyerSubscription->user_id);
        $this->assertSame(SubscriptionStatus::Pending, $buyerSubscription->status);

        $project = $this->publishedProject($finance);

        $this->actingAs($finance)->post(route('staff.memberships.activate', $buyerSubscription))->assertRedirect();
        $this->actingAs($buyer)->get('/investor')->assertOk();
        $this->actingAs($buyer)->get(route('intelligence.projects.show', $project))->assertOk()->assertSee('Unconfirmed spur note');
        $this->assertSame(15000, $buyerSubscription->events()->where('action', 'activated')->first()->amount_cents);
        $this->assertStringContainsString('No payment was collected', $buyerSubscription->events()->where('action', 'activated')->first()->note);

        $ends = $buyerSubscription->fresh()->ends_at->copy();
        $this->actingAs($finance)->post(route('staff.memberships.renew', $buyerSubscription))->assertRedirect();
        $this->assertTrue($buyerSubscription->fresh()->ends_at->greaterThan($ends));

        $this->actingAs($buyer)->post(route('buyer.subscription.cancel'))->assertRedirect();
        $this->actingAs($buyer)->get('/investor')->assertForbidden();
        $this->actingAs($buyer)->get(route('intelligence.projects.show', $project))->assertOk()->assertDontSee('Unconfirmed spur note');

        $brokerSubscription = Subscription::query()->where('user_id', $broker->id)->firstOrFail();
        $this->actingAs($finance)->post(route('staff.memberships.activate', $brokerSubscription))->assertRedirect();
        $this->assertTrue($broker->fresh()->hasPermission('workspace.broker'));
        $brokerSubscription->update(['ends_at' => now()->subDay()]);
        $this->actingAs($broker)->get(route('broker.subscription.show'))->assertOk();
        $this->assertSame(SubscriptionStatus::Expired, $brokerSubscription->fresh()->status);
        $this->actingAs($broker)->get('/investor')->assertForbidden();
    }

    private function publishedProject(User $finance): DevelopmentProject
    {
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $desk = app(PublicationDesk::class);

        $project = DevelopmentProject::query()->create([
            'reference' => $desk->reference('DP', DevelopmentProject::class),
            'slug' => $desk->slug('Recorded port works', DevelopmentProject::class),
            'name' => 'Recorded port works',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Lamu County coastline',
            'summary' => 'A public summary.',
            'research_notes' => 'Unconfirmed spur note',
            'source_name' => 'County notice',
            'publication_status' => 'review',
            'created_by' => $finance->id,
        ]);

        $this->actingAs($editor)->post(route('staff.projects.publish', $project))->assertRedirect();

        return $project->fresh();
    }
}
