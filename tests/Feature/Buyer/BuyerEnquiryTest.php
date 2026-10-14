<?php

namespace Tests\Feature\Buyer;

use App\Enums\BrokerIntroductionStatus;
use App\Enums\EnquiryStatus;
use App\Enums\LeadStatus;
use App\Models\BrokerIntroduction;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use App\Notifications\EnquiryAssignedNotification;
use App\Notifications\EnquiryResponseNotification;
use App\Notifications\EnquirySubmittedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Foundation\FoundationTestCase;

class BuyerEnquiryTest extends FoundationTestCase
{
    public function test_a_buyer_can_save_and_remove_only_their_own_properties(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $other = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create(['title' => 'Manda plot']);

        $this->post(route('land.saved.store', $property))->assertRedirect(route('login'));

        $this->actingAs($buyer)
            ->post(route('land.saved.store', $property))
            ->assertRedirect()
            ->assertSessionHas('status', 'Property saved.');

        $this->actingAs($buyer)->post(route('land.saved.store', $property))->assertRedirect();

        $this->assertSame(1, $buyer->savedProperties()->count());

        $this->actingAs($buyer)
            ->get(route('buyer.saved.index'))
            ->assertOk()
            ->assertSee('Manda plot');

        $this->actingAs($other)
            ->get(route('buyer.saved.index'))
            ->assertOk()
            ->assertDontSee('Manda plot');

        $this->actingAs(User::factory()->withRoles('broker')->create())
            ->get(route('buyer.saved.index'))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->delete(route('buyer.saved.destroy', $property))
            ->assertRedirect();

        $this->assertSame(0, $buyer->savedProperties()->count());
    }

    public function test_an_enquiry_is_tied_to_the_signed_in_buyer_and_keeps_the_campaign(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();
        $other = User::factory()->withRoles('buyer')->create();
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create();
        $property = Property::factory()->published()->create(['title' => 'Shela holding']);
        $broker = User::factory()->withRoles('broker')->create();

        $introduction = BrokerIntroduction::factory()->create([
            'broker_id' => $broker->id,
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => BrokerIntroductionStatus::Active,
        ]);
        $before = $introduction->fresh()->only(['reference', 'broker_id', 'buyer_id', 'property_id', 'status', 'expires_at', 'updated_at']);

        $this->actingAs($buyer)
            ->withHeader('Referer', 'https://example.com/lamu-guide')
            ->post(route('land.enquiries.store', $property), [
                'message' => 'Please tell me whether this plot has road access.',
                'campaign' => 'manda-may',
                'buyer_id' => $other->id,
            ])
            ->assertRedirect(route('buyer.enquiries.index'));

        $enquiry = Enquiry::query()->first();

        $this->assertNotNull($enquiry);
        $this->assertSame($buyer->id, $enquiry->buyer_id);
        $this->assertSame($property->id, $enquiry->property_id);
        $this->assertSame($introduction->id, $enquiry->broker_introduction_id);
        $this->assertSame('manda-may', $enquiry->campaign);
        $this->assertSame('https://example.com/lamu-guide', $enquiry->referrer_url);
        $this->assertSame(EnquiryStatus::New, $enquiry->status);

        $introduction->refresh();
        $this->assertSame($before['status'], $introduction->status);
        $this->assertSame($before['broker_id'], $introduction->broker_id);
        $this->assertSame($before['updated_at']?->toISOString(), $introduction->updated_at?->toISOString());

        Notification::assertSentTo($desk, EnquirySubmittedNotification::class);

        $this->actingAs($buyer)->get(route('buyer.enquiries.index'))->assertOk()->assertSee('Shela holding');
        $this->actingAs($other)->get(route('buyer.enquiries.show', $enquiry))->assertForbidden();
        $this->actingAs($other)->get(route('buyer.enquiries.index'))->assertOk()->assertDontSee('Shela holding');
    }

    public function test_spam_and_repeat_enquiries_are_rejected(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create();
        $message = 'Please tell me whether this plot has road access.';

        $this->actingAs($buyer)
            ->post(route('land.enquiries.store', $property), [
                'message' => $message,
                'website' => 'https://spam.test',
            ])
            ->assertSessionHasErrors('website');

        $this->assertSame(0, Enquiry::query()->count());

        $this->actingAs($buyer)
            ->post(route('land.enquiries.store', $property), ['message' => $message])
            ->assertRedirect(route('buyer.enquiries.index'));

        $this->actingAs($buyer)
            ->from(route('land.item', $property))
            ->post(route('land.enquiries.store', $property), ['message' => $message])
            ->assertSessionHasErrors('message');

        $this->assertSame(1, Enquiry::query()->count());

        RateLimiter::clear(md5('enquiries'.$buyer->id));

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($buyer)
                ->post(route('land.enquiries.store', $property), [
                    'message' => 'Please share the survey details for attempt '.$attempt.'.',
                ])
                ->assertRedirect();
        }

        $this->actingAs($buyer)
            ->post(route('land.enquiries.store', $property), [
                'message' => 'Please share the survey details for the blocked attempt.',
            ])
            ->assertStatus(429);
    }

    public function test_staff_assignment_and_responses_follow_the_desk_and_the_assignee(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create();
        $agent = User::factory()->withRoles('staff')->create();
        $outsider = User::factory()->withRoles('staff')->create();
        $property = Property::factory()->published()->create(['title' => 'Hindi block']);

        $enquiry = Enquiry::factory()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
        ]);

        $this->actingAs($outsider)->get(route('staff.enquiries.index'))->assertForbidden();
        $this->actingAs($outsider)->get(route('staff.enquiries.show', $enquiry))->assertForbidden();
        $this->actingAs($buyer)->get(route('staff.enquiries.show', $enquiry))->assertForbidden();

        $this->actingAs($desk)
            ->put(route('staff.enquiries.assign', $enquiry), ['assigned_to' => $agent->id])
            ->assertRedirect()
            ->assertSessionHas('status', 'Enquiry assigned.');

        $enquiry->refresh();
        $this->assertSame($agent->id, (int) $enquiry->assigned_to);
        $this->assertSame(EnquiryStatus::Acknowledged, $enquiry->status);
        Notification::assertSentTo($agent, EnquiryAssignedNotification::class);

        $this->actingAs($agent)->get(route('staff.enquiries.show', $enquiry))->assertOk()->assertSee('Hindi block');

        $this->actingAs($agent)
            ->put(route('staff.enquiries.assign', $enquiry), ['assigned_to' => $desk->id])
            ->assertForbidden();

        $this->actingAs($agent)
            ->put(route('staff.enquiries.update', $enquiry), [
                'status' => EnquiryStatus::InProgress->value,
                'lead_status' => LeadStatus::Qualified->value,
            ])
            ->assertRedirect();

        $this->assertSame(LeadStatus::Qualified, $enquiry->refresh()->lead_status);

        $this->actingAs($agent)
            ->post(route('staff.enquiries.respond', $enquiry), [
                'body' => 'A surveyor can walk the boundary next week.',
                'visible_to_buyer' => '1',
            ])
            ->assertRedirect();

        $this->actingAs($agent)
            ->post(route('staff.enquiries.respond', $enquiry), [
                'body' => 'Internal note about the owner.',
                'visible_to_buyer' => '0',
            ])
            ->assertRedirect();

        Notification::assertSentTo($buyer, EnquiryResponseNotification::class);
        Notification::assertSentToTimes($buyer, EnquiryResponseNotification::class, 1);

        $this->actingAs($buyer)
            ->get(route('buyer.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee('A surveyor can walk the boundary next week.')
            ->assertDontSee('Internal note about the owner.');

        $this->assertSame(EnquiryStatus::Responded, $enquiry->refresh()->status);
    }
}
