<?php

namespace Tests\Feature\Buyer;

use App\Enums\DocumentAccessStatus;
use App\Enums\OfferStatus;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyMatchStatus;
use App\Enums\PropertyStatus;
use App\Enums\SiteVisitStatus;
use App\Models\DocumentAccessRequest;
use App\Models\Offer;
use App\Models\Property;
use App\Models\PropertyMatch;
use App\Models\SiteVisit;
use App\Models\SourcingMatch;
use App\Models\SourcingRequest;
use App\Models\User;
use App\Notifications\MatchApprovedNotification;
use App\Notifications\OfferStatusNotification;
use App\Notifications\OfferSubmittedNotification;
use App\Notifications\ViewingAcknowledgedNotification;
use App\Services\Buyer\InterestWorkflow;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Foundation\FoundationTestCase;

class BuyerInterestTest extends FoundationTestCase
{
    public function test_staff_approval_builds_a_private_shortlist_with_rejection_feedback(): void
    {
        Notification::fake();

        [$buyer, $other, $desk, $property, $sourcingMatch] = $this->approvedSetup();

        $this->actingAs($desk)
            ->post(route('staff.sourcing.approve', [$sourcingMatch->sourcingRequest, $sourcingMatch]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Match added to the buyer shortlist.');

        $match = PropertyMatch::query()->first();
        $this->assertSame(PropertyMatchStatus::Shortlisted, $match->status);
        $this->assertSame($buyer->id, $match->buyer_id);
        $this->assertSame($property->id, $match->property_id);
        Notification::assertSentTo($buyer, MatchApprovedNotification::class);

        $this->actingAs($buyer)->get(route('buyer.matches.show', $match))->assertOk()->assertSee($property->title)->assertSee('Exact directions stay hidden');
        $this->actingAs($other)->get(route('buyer.matches.show', $match))->assertForbidden();
        $this->actingAs($other)->get(route('buyer.matches.index'))->assertOk()->assertDontSee($property->title);

        $this->actingAs($buyer)
            ->post(route('buyer.matches.reject', $match), ['buyer_feedback' => 'The access road is too narrow.'])
            ->assertRedirect();

        $this->assertSame(PropertyMatchStatus::Rejected, $match->refresh()->status);
        $this->assertSame('The access road is too narrow.', $match->buyer_feedback);
    }

    public function test_visit_transitions_release_directions_only_after_confirmation(): void
    {
        Notification::fake();

        [$buyer, , $desk, $property] = $this->approvedSetup();
        $agent = User::factory()->withRoles('staff')->create();
        $match = app(InterestWorkflow::class)->approve(SourcingMatch::query()->first(), $desk);

        $this->actingAs($buyer)
            ->post(route('buyer.matches.documents.store', $match), ['note' => 'Please share the title search.'])
            ->assertRedirect();

        $access = DocumentAccessRequest::query()->first();

        $this->actingAs($desk)
            ->put(route('staff.documents.update', $access), ['status' => DocumentAccessStatus::Approved->value])
            ->assertRedirect();

        $this->actingAs($buyer)
            ->get(route('buyer.matches.show', $match))
            ->assertOk()
            ->assertSee('Approved')
            ->assertSee('Exact directions stay hidden')
            ->assertDontSee('40.2222222');

        $this->actingAs($buyer)
            ->post(route('buyer.matches.visits.store', $match), [
                'requested_for' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'notes' => 'Morning visit.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Site visit requested.');

        $visit = SiteVisit::query()->first();

        $this->actingAs($buyer)
            ->put(route('staff.visits.update', $visit), ['status' => SiteVisitStatus::Confirmed->value])
            ->assertForbidden();

        $this->actingAs($desk)
            ->from(route('staff.visits.index'))
            ->put(route('staff.visits.update', $visit), ['status' => SiteVisitStatus::Completed->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(SiteVisitStatus::Requested, $visit->refresh()->status);

        $this->actingAs($desk)
            ->put(route('staff.visits.assign', $visit), ['assigned_to' => $agent->id])
            ->assertRedirect();

        $this->actingAs($agent)
            ->put(route('staff.visits.update', $visit), [
                'status' => SiteVisitStatus::Confirmed->value,
                'confirmed_for' => now()->addDays(4)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $this->actingAs($buyer)
            ->get(route('buyer.matches.show', $match))
            ->assertOk()
            ->assertSee('40.2222222')
            ->assertSee('-2.1111111');

        $this->actingAs($agent)
            ->put(route('staff.visits.update', $visit), ['status' => SiteVisitStatus::Completed->value])
            ->assertRedirect();

        $this->actingAs($buyer)
            ->post(route('buyer.visits.cancel', $visit))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->post(route('buyer.visits.acknowledge', $visit))
            ->assertRedirect()
            ->assertSessionHas('status', 'Viewing acknowledged.');

        $this->assertNotNull($visit->refresh()->buyer_acknowledged_at);
        Notification::assertSentTo($agent, ViewingAcknowledgedNotification::class);
        $this->assertSame(PropertyAvailability::Available, $property->refresh()->availability);
    }

    public function test_an_accepted_offer_does_not_mark_the_property_sold(): void
    {
        Notification::fake();

        [$buyer, , $desk, $property] = $this->approvedSetup();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $match = app(InterestWorkflow::class)->approve(SourcingMatch::query()->first(), $desk);

        $this->actingAs($buyer)
            ->post(route('buyer.matches.offers.store', $match), [
                'amount' => 1_500_000,
                'message' => 'Subject to a survey.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Offer submitted.');

        $offer = Offer::query()->first();
        Notification::assertSentTo($sales, OfferSubmittedNotification::class);

        $this->actingAs($buyer)
            ->put(route('staff.offers.update', $offer), ['status' => OfferStatus::Accepted->value])
            ->assertForbidden();

        $this->actingAs($sales)
            ->put(route('staff.offers.update', $offer), ['status' => OfferStatus::Accepted->value])
            ->assertRedirect();

        $property->refresh();
        $this->assertSame(OfferStatus::Accepted, $offer->refresh()->status);
        $this->assertSame(PropertyStatus::Published, $property->status);
        $this->assertSame(PropertyAvailability::Available, $property->availability);
        Notification::assertSentTo($buyer, OfferStatusNotification::class);

        $this->actingAs($buyer)
            ->get(route('buyer.dashboard'))
            ->assertOk()
            ->assertSee('Offer Accepted for '.$property->title)
            ->assertSee('Open offers');
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Property, 4: SourcingMatch}
     */
    private function approvedSetup(): array
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $other = User::factory()->withRoles('buyer')->create();
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.sourcing', 'staff.buyers')->create();
        $property = Property::factory()->published()->create([
            'title' => 'Hindi block',
            'public_latitude' => -2.27,
            'public_longitude' => 40.9,
            'restricted_latitude' => -2.1111111,
            'restricted_longitude' => 40.2222222,
        ]);
        $request = SourcingRequest::query()->create([
            'reference' => 'SR-TEST0001',
            'buyer_id' => $buyer->id,
            'status' => 'submitted',
            'location_label' => 'Shela',
            'centre_latitude' => -2.27,
            'centre_longitude' => 40.9,
            'radius_km' => 5,
            'centre_wkt' => 'POINT(40.9 -2.27)',
            'submitted_at' => now(),
        ]);
        $sourcingMatch = $request->matches()->create([
            'property_id' => $property->id,
            'band' => 'exact',
            'distance_km' => 0,
            'reasons' => [],
        ]);

        return [$buyer, $other, $desk, $property, $sourcingMatch];
    }
}
