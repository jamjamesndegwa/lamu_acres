<?php

namespace Tests\Feature\Broker;

use App\Enums\BrokerIntroductionStatus;
use App\Enums\CommissionFunding;
use App\Enums\CommissionStatus;
use App\Enums\QualifyingEvent;
use App\Enums\SalesOrigin;
use App\Models\BrokerIntroduction;
use App\Models\CommissionAgreement;
use App\Models\Property;
use App\Models\SalesTransaction;
use App\Models\SourcingRequest;
use App\Models\User;
use Tests\Feature\Foundation\FoundationTestCase;

class BrokerPartnershipTest extends FoundationTestCase
{
    public function test_broker_desk_shows_owned_records_and_hides_buyer_identity_on_the_board(): void
    {
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $other = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $buyer = User::factory()->withRoles('buyer')->create(['name' => 'Secret Buyer Name']);
        $property = Property::factory()->published()->create(['title' => 'Partner parcel']);

        $this->actingAs($buyer)->get(route('broker.requirements.index'))->assertForbidden();

        SourcingRequest::query()->create([
            'reference' => 'SR-BOARD01',
            'buyer_id' => $buyer->id,
            'status' => 'submitted',
            'location_label' => 'Hindi shore',
            'notes' => 'Call Secret Buyer Name tonight',
            'submitted_at' => now(),
        ]);

        $this->actingAs($broker)
            ->get(route('broker.dashboard'))
            ->assertOk()
            ->assertSee('Listings')
            ->assertSee('My Listings')
            ->assertSee('Register a Buyer')
            ->assertSee('My Referrals')
            ->assertSee('Notifications')
            ->assertSee('Profile');

        $this->actingAs($broker)
            ->get(route('broker.requirements.index'))
            ->assertOk()
            ->assertSee('SR-BOARD01')
            ->assertSee('Hindi shore')
            ->assertDontSee('Secret Buyer Name');

        $accepted = BrokerIntroduction::factory()->create([
            'broker_id' => $broker->id,
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => BrokerIntroductionStatus::Accepted,
            'buyer_name' => 'Named on the introduction',
        ]);
        $waiting = BrokerIntroduction::factory()->create([
            'broker_id' => $broker->id,
            'property_id' => $property->id,
            'status' => BrokerIntroductionStatus::Submitted,
        ]);

        $visible = SalesTransaction::query()->create([
            'reference' => 'SL-VISIBLE',
            'property_id' => $property->id,
            'origin' => SalesOrigin::Introduction,
            'broker_introduction_id' => $accepted->id,
            'stage' => 'viewing',
            'opened_at' => now(),
        ]);
        $visible->milestones()->create(['name' => 'Staff recorded viewing']);

        SalesTransaction::query()->create([
            'reference' => 'SL-WAITING',
            'property_id' => $property->id,
            'origin' => SalesOrigin::Introduction,
            'broker_introduction_id' => $waiting->id,
            'opened_at' => now(),
        ]);

        $acceptedTerms = CommissionAgreement::query()->create([
            'reference' => 'CA-ACCEPTED',
            'sales_transaction_id' => $visible->id,
            'broker_id' => $broker->id,
            'property_id' => $property->id,
            'earned_amount' => 50000,
            'funding_party' => CommissionFunding::Seller,
            'qualifying_event' => QualifyingEvent::SaleCompleted,
            'status' => CommissionStatus::Earned,
            'accepted_at' => now(),
        ]);
        $proposed = CommissionAgreement::query()->create([
            'reference' => 'CA-PROPOSED',
            'sales_transaction_id' => $visible->id,
            'broker_id' => $broker->id,
            'property_id' => $property->id,
            'funding_party' => CommissionFunding::Seller,
            'qualifying_event' => QualifyingEvent::SaleCompleted,
            'status' => CommissionStatus::Proposed,
            'open_claim' => 'proposed-only',
        ]);

        $this->actingAs($broker)
            ->get(route('broker.deals.index'))
            ->assertOk()
            ->assertSee('SL-VISIBLE')
            ->assertDontSee('SL-WAITING');

        $this->actingAs($broker)
            ->get(route('broker.deals.show', $visible))
            ->assertOk()
            ->assertSee('Staff recorded viewing')
            ->assertDontSee('Apply stage');

        $this->actingAs($other)->get(route('broker.deals.show', $visible))->assertNotFound();

        $this->actingAs($broker)
            ->get(route('broker.agreements.index'))
            ->assertOk()
            ->assertSee('CA-ACCEPTED')
            ->assertDontSee('CA-PROPOSED');

        $this->actingAs($broker)->get(route('broker.agreements.show', $proposed))->assertNotFound();

        $this->actingAs($broker)
            ->get(route('broker.earnings.index'))
            ->assertOk()
            ->assertSee('CA-ACCEPTED')
            ->assertSee('KSh 50,000')
            ->assertSee('does not calculate a commission');
    }
}
