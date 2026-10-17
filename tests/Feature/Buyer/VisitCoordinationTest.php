<?php

namespace Tests\Feature\Buyer;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Enums\SiteVisitStatus;
use App\Models\MessageDelivery;
use App\Models\Property;
use App\Models\PropertyVisitRequest;
use App\Models\User;
use App\Notifications\SiteVisitUpdatedNotification;
use App\Services\Buyer\InterestWorkflow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Foundation\FoundationTestCase;

class VisitCoordinationTest extends FoundationTestCase
{
    public function test_buyer_can_confirm_a_visit_and_request_a_reschedule_that_staff_accepts(): void
    {
        Notification::fake();
        Mail::fake();

        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => true]);
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create();
        $property = Property::factory()->published()->create(['title' => 'Manda Visit Plot']);
        $visitRequest = PropertyVisitRequest::query()->create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'preferred_on' => now()->addDays(2)->toDateString(),
            'note' => 'Please call before arriving.',
        ]);
        $visit = app(InterestWorkflow::class)->requestListingVisit($visitRequest);
        $confirmedFor = now()->addDays(3)->setTime(10, 30);

        $this->actingAs($desk)
            ->put(route('staff.visits.update', $visit), [
                'status' => SiteVisitStatus::Confirmed->value,
                'confirmed_for' => $confirmedFor->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('message_deliveries', [
            'user_id' => $buyer->id,
            'event' => MessageEvent::SiteVisitConfirmed->value,
            'channel' => MessageChannel::Email->value,
        ]);
        $email = MessageDelivery::query()
            ->where('user_id', $buyer->id)
            ->where('event', MessageEvent::SiteVisitConfirmed)
            ->where('channel', MessageChannel::Email)
            ->sole();
        $this->assertStringContainsString($confirmedFor->toDayDateTimeString(), $email->body);
        Notification::assertSentTo($buyer, SiteVisitUpdatedNotification::class);

        $this->actingAs($buyer)
            ->post(route('buyer.visits.confirm-details', $visit))
            ->assertRedirect()
            ->assertSessionHas('status', 'Appointment details confirmed.');
        $this->assertNotNull($visit->refresh()->buyer_confirmed_at);
        Notification::assertSentTo($desk, SiteVisitUpdatedNotification::class);

        $preferredFor = now()->addDays(5)->setTime(14, 0);
        $this->actingAs($buyer)
            ->post(route('buyer.visits.reschedule', $visit), [
                'preferred_for' => $preferredFor->format('Y-m-d\\TH:i'),
                'note' => 'I am unavailable at the confirmed time.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Your reschedule request was sent to the visit desk. Your current appointment remains confirmed until staff responds.');

        $this->assertDatabaseHas('site_visits', [
            'id' => $visit->id,
            'status' => SiteVisitStatus::Confirmed->value,
            'reschedule_preferred_for' => $preferredFor->format('Y-m-d H:i:s'),
            'reschedule_note' => 'I am unavailable at the confirmed time.',
        ]);
        $this->actingAs($buyer)
            ->get(route('buyer.visits.index'))
            ->assertOk()
            ->assertSee('Your current appointment remains confirmed until staff responds.');

        $newConfirmedFor = now()->addDays(6)->setTime(9, 15);
        $this->actingAs($desk)
            ->put(route('staff.visits.reschedule.resolve', $visit), [
                'decision' => 'accept',
                'confirmed_for' => $newConfirmedFor->format('Y-m-d H:i:s'),
                'response' => 'The new appointment time is confirmed.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Reschedule request resolved and buyer notified.');

        $visit->refresh();
        $this->assertSame($newConfirmedFor->timestamp, $visit->confirmed_for->timestamp);
        $this->assertNull($visit->reschedule_requested_at);
        $this->assertNull($visit->reschedule_preferred_for);
        $this->assertNull($visit->buyer_confirmed_at);
        Notification::assertSentTo($buyer, SiteVisitUpdatedNotification::class, fn (SiteVisitUpdatedNotification $notification): bool => str_contains($notification->message, $newConfirmedFor->toDayDateTimeString()));
    }

    public function test_only_the_visit_buyer_can_confirm_or_reschedule_their_appointment(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $otherBuyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create();
        $visitRequest = PropertyVisitRequest::query()->create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'preferred_on' => now()->addDays(2)->toDateString(),
        ]);
        $visit = app(InterestWorkflow::class)->requestListingVisit($visitRequest);
        $visit->forceFill([
            'status' => SiteVisitStatus::Confirmed,
            'confirmed_for' => now()->addDays(3),
        ])->save();

        $this->actingAs($otherBuyer)
            ->post(route('buyer.visits.confirm-details', $visit))
            ->assertForbidden();
        $this->actingAs($otherBuyer)
            ->post(route('buyer.visits.reschedule', $visit), [
                'preferred_for' => now()->addDays(4)->format('Y-m-d\\TH:i'),
            ])
            ->assertForbidden();

        $this->assertNull($visit->fresh()->buyer_confirmed_at);
        $this->assertNull($visit->fresh()->reschedule_requested_at);
    }

    public function test_buyer_cannot_reschedule_to_a_past_time(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create();
        $visitRequest = PropertyVisitRequest::query()->create([
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'preferred_on' => now()->addDays(2)->toDateString(),
        ]);
        $visit = app(InterestWorkflow::class)->requestListingVisit($visitRequest);
        $visit->forceFill([
            'status' => SiteVisitStatus::Confirmed,
            'confirmed_for' => now()->addDays(3),
        ])->save();

        $this->actingAs($buyer)
            ->post(route('buyer.visits.reschedule', $visit), [
                'preferred_for' => Carbon::yesterday()->format('Y-m-d\\TH:i'),
            ])
            ->assertSessionHasErrors('preferred_for');

        $this->assertNull($visit->fresh()->reschedule_requested_at);
    }
}
