<?php

namespace Tests\Feature\Admin;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\CommissionFunding;
use App\Enums\CommissionStatus;
use App\Enums\PropertyStatus;
use App\Enums\PublicationStatus;
use App\Enums\QualifyingEvent;
use App\Enums\SalesOrigin;
use App\Enums\VerificationLevel;
use App\Enums\VerificationOverride;
use App\Models\CapabilityApplication;
use App\Models\CommissionAgreement;
use App\Models\DevelopmentProject;
use App\Models\Property;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Models\VerifiedSale;
use Tests\Feature\Foundation\FoundationTestCase;

class ApprovalsCentreTest extends FoundationTestCase
{
    public function test_other_workspaces_cannot_open_the_approvals_centre(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $workspaceOnly = User::factory()->withPermissions('workspace.admin')->create();

        $this->actingAs($buyer)->get(route('admin.approvals.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('admin.approvals.index'))->assertForbidden();
        $this->actingAs($workspaceOnly)->get(route('admin.approvals.index'))->assertForbidden();
    }

    public function test_administrator_opens_every_approval_queue_except_commission_payment(): void
    {
        $admin = User::factory()->withRoles('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.approvals.index'))
            ->assertOk()
            ->assertSee('Approvals Centre')
            ->assertSee('All Pending (0)')
            ->assertSee('Property Listings (0)')
            ->assertSee('Broker/Seller Applications (0)')
            ->assertSee('Broker Referrals (0)')
            ->assertSee('Marketing Authorities (0)')
            ->assertSee('Verification Exceptions (0)')
            ->assertSee('Sourcing Matches (0)')
            ->assertSee('Document Access (0)')
            ->assertSee('Intelligence Publications (0)')
            ->assertSee('This queue is empty.')
            ->assertDontSee('Commission Decisions')
            ->assertDontSee('No approval category is open');
    }

    public function test_super_administrator_sees_desk_categories_except_commission(): void
    {
        $admin = User::factory()->withRoles('super_admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.approvals.index'))
            ->assertOk()
            ->assertSee('Property Listings (0)')
            ->assertSee('Broker/Seller Applications (0)')
            ->assertSee('Broker Referrals (0)')
            ->assertSee('Marketing Authorities (0)')
            ->assertSee('Verification Exceptions (0)')
            ->assertSee('Sourcing Matches (0)')
            ->assertSee('Document Access (0)')
            ->assertSee('Intelligence Publications (0)')
            ->assertSee('All Pending (0)')
            ->assertDontSee('Commission Decisions');
    }

    public function test_queue_counts_exclude_closed_records_and_link_to_review_pages(): void
    {
        $controller = $this->controller();
        $broker = User::factory()->withRoles('broker')->create();
        $applicant = User::factory()->withRoles('buyer')->create();

        $first = Property::factory()->create([
            'reference' => 'LA-QUEUE-ONE',
            'submitted_by' => $broker->id,
            'status' => PropertyStatus::Review,
        ]);
        Property::factory()->create([
            'reference' => 'LA-QUEUE-TWO',
            'submitted_by' => $broker->id,
            'status' => PropertyStatus::Review,
        ]);
        Property::factory()->create([
            'reference' => 'LA-ALREADY-PUBLISHED',
            'submitted_by' => $broker->id,
            'status' => PropertyStatus::Published,
        ]);

        CapabilityApplication::query()->create([
            'user_id' => $applicant->id,
            'capability' => 'seller',
            'status' => CapabilityApplicationStatus::Pending,
            'statement' => 'I hold land at Mokowe.',
        ]);
        CapabilityApplication::query()->create([
            'user_id' => $broker->id,
            'capability' => 'seller',
            'status' => CapabilityApplicationStatus::Approved,
            'statement' => 'Already decided.',
        ]);

        $exception = Property::factory()->create(['reference' => 'LA-EXCEPTION']);
        $exception->passport()->create([
            'level' => VerificationLevel::Advertised,
            'override' => VerificationOverride::Flagged,
        ]);

        $earned = $this->commission($broker, CommissionStatus::Earned, 'CA-EARNED');
        $this->commission($broker, CommissionStatus::Proposed, 'CA-PROPOSED');

        DevelopmentProject::query()->create([
            'reference' => 'DP-REVIEW',
            'slug' => 'dp-review',
            'name' => 'Recorded road study',
            'category' => 'road',
            'project_status' => 'proposed',
            'coverage' => 'Lamu County',
            'summary' => 'A public summary.',
            'source_name' => 'County notice',
            'publication_status' => PublicationStatus::Review,
            'created_by' => $controller->id,
        ]);
        DevelopmentProject::query()->create([
            'reference' => 'DP-DRAFT',
            'slug' => 'dp-draft',
            'name' => 'Unsubmitted study',
            'category' => 'road',
            'project_status' => 'proposed',
            'coverage' => 'Lamu County',
            'summary' => 'Still a draft.',
            'source_name' => 'County notice',
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $controller->id,
        ]);

        $this->actingAs($controller)
            ->get(route('admin.approvals.index'))
            ->assertOk()
            ->assertSee('All Pending (6)')
            ->assertSee('Property Listings (2)')
            ->assertSee('Broker/Seller Applications (1)')
            ->assertSee('Verification Exceptions (1)')
            ->assertSee('Commission Decisions (1)')
            ->assertSee('Intelligence Publications (1)')
            ->assertSee('LA-QUEUE-ONE')
            ->assertSee('LA-QUEUE-TWO')
            ->assertSee('LA-EXCEPTION')
            ->assertSee('CA-EARNED')
            ->assertSee('DP-REVIEW')
            ->assertSee('APP-')
            ->assertDontSee('LA-ALREADY-PUBLISHED')
            ->assertDontSee('CA-PROPOSED')
            ->assertDontSee('DP-DRAFT')
            ->assertDontSee('Already decided.')
            ->assertSee(route('staff.listings.show', ['property' => $first, 'return' => 'approvals']), false)
            ->assertSee('Review and publish')
            ->assertDontSee(route('staff.listings.approve', $first))
            ->assertDontSee(route('staff.settlements.approve', $earned))
            ->assertDontSee(route('staff.settlements.pay', $earned));

        $this->actingAs($controller)
            ->get(route('staff.commissions.show', ['commissionAgreement' => $earned, 'return' => 'approvals']))
            ->assertOk()
            ->assertSee(route('staff.settlements.approve', $earned), false)
            ->assertSee('Approve as payable')
            ->assertDontSee('Record paid');
    }

    public function test_search_and_unknown_category_are_actionable(): void
    {
        $controller = $this->controller();
        $broker = User::factory()->withRoles('broker')->create(['name' => 'Amina Broker']);

        Property::factory()->create([
            'reference' => 'LA-FIND-ME',
            'submitted_by' => $broker->id,
            'status' => PropertyStatus::Review,
        ]);
        Property::factory()->create([
            'reference' => 'LA-HIDE-ME',
            'submitted_by' => $broker->id,
            'status' => PropertyStatus::Draft,
        ]);

        $this->actingAs($controller)
            ->get(route('admin.approvals.index', ['q' => 'LA-FIND-ME']))
            ->assertOk()
            ->assertSee('LA-FIND-ME')
            ->assertDontSee('LA-HIDE-ME');

        $this->actingAs($controller)
            ->get(route('admin.approvals.index', ['tab' => 'listings', 'status' => 'Review']))
            ->assertOk()
            ->assertSee('LA-FIND-ME')
            ->assertDontSee('LA-HIDE-ME');

        $this->actingAs($controller)
            ->get(route('admin.approvals.index', ['q' => 'no-such-record']))
            ->assertOk()
            ->assertSee('No records match this search.');

        $this->actingAs($controller)
            ->get(route('admin.approvals.index', ['q' => str_repeat('a', 81)]))
            ->assertSessionHasErrors('q');

        $this->actingAs($controller)
            ->get(route('admin.approvals.index', ['tab' => 'not-a-category']))
            ->assertRedirect(route('admin.approvals.index'))
            ->assertSessionHasErrors('tab');
    }

    public function test_verified_sale_publication_is_not_offered_from_the_queue(): void
    {
        $controller = User::factory()->withRoles('admin')->withPermissions('workspace.staff', 'intelligence.publish')->create();
        $sale = VerifiedSale::query()->create([
            'reference' => 'VS-REVIEW',
            'acreage' => 2.5,
            'price_amount' => 1000000,
            'sold_on' => now()->toDateString(),
            'source_name' => 'Registry extract',
            'publication_status' => PublicationStatus::Review,
            'created_by' => $controller->id,
        ]);

        $this->actingAs($controller)
            ->get(route('admin.approvals.index', ['tab' => 'intelligence']))
            ->assertOk()
            ->assertSee('Intelligence Publications (1)')
            ->assertSee('VS-REVIEW')
            ->assertSee('No review page exists. Publication stays on the price desk.')
            ->assertSee(route('staff.prices.index'), false)
            ->assertDontSee('Review and publish')
            ->assertDontSee(route('staff.prices.sales.publish', $sale));
    }

    public function test_commission_queue_stays_hidden_without_the_finance_desk(): void
    {
        $approver = User::factory()->withRoles('admin')->withPermissions('workspace.staff', 'commissions.approve', 'staff.sales')->create();

        $this->actingAs($approver)
            ->get(route('admin.approvals.index'))
            ->assertOk()
            ->assertDontSee('Commission Decisions')
            ->assertSee('the existing payable approval route stays on the finance settlement desk');
    }

    public function test_application_decision_returns_to_the_updated_queue(): void
    {
        $controller = User::factory()->withRoles('admin')->withPermissions('workspace.staff', 'staff.brokerage')->create();
        $applicant = User::factory()->withRoles('buyer')->create();
        $application = CapabilityApplication::query()->create([
            'user_id' => $applicant->id,
            'capability' => 'broker',
            'status' => CapabilityApplicationStatus::Pending,
            'statement' => 'I introduce buyers in Lamu.',
        ]);

        $this->actingAs($controller)
            ->put(route('staff.applications.update', $application), [
                'status' => 'rejected',
                'decision_reason' => 'The statement does not identify a brokerage.',
                'return' => 'approvals',
            ])
            ->assertRedirect(route('admin.approvals.index', ['tab' => 'applications']));

        $this->assertSame(CapabilityApplicationStatus::Rejected, $application->fresh()->status);

        $this->actingAs($controller)
            ->get(route('admin.approvals.index', ['tab' => 'applications']))
            ->assertOk()
            ->assertSee('Broker/Seller Applications (0)')
            ->assertSee('This queue is empty.');
    }

    private function controller(): User
    {
        return User::factory()->withRoles('admin')->withPermissions(
            'workspace.staff',
            'staff.properties',
            'staff.brokerage',
            'staff.verification',
            'staff.finance',
            'commissions.approve',
            'intelligence.publish',
        )->create();
    }

    private function commission(User $broker, CommissionStatus $status, string $reference): CommissionAgreement
    {
        $property = Property::factory()->create();
        $transaction = SalesTransaction::query()->create([
            'reference' => 'SL-'.$reference,
            'property_id' => $property->id,
            'origin' => SalesOrigin::Enquiry,
            'opened_at' => now(),
        ]);

        return CommissionAgreement::query()->create([
            'reference' => $reference,
            'sales_transaction_id' => $transaction->id,
            'broker_id' => $broker->id,
            'property_id' => $property->id,
            'earned_amount' => 50000,
            'funding_party' => CommissionFunding::Seller,
            'qualifying_event' => QualifyingEvent::SaleCompleted,
            'status' => $status,
            'open_claim' => $reference,
        ]);
    }
}
