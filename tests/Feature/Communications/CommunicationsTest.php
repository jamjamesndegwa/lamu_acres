<?php

namespace Tests\Feature\Communications;

use App\Contracts\Communications\SmsSender;
use App\Enums\CapabilityApplicationStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Enums\PartnerCapability;
use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Events\EnquirySubmitted;
use App\Models\CapabilityApplication;
use App\Models\Enquiry;
use App\Models\MessageDelivery;
use App\Models\MessageTemplate;
use App\Models\NotificationPreference;
use App\Models\Property;
use App\Models\User;
use App\Notifications\TemplatedDeskNotification;
use App\Services\Access\CapabilityReviewer;
use App\Services\Broker\PropertySubmission;
use App\Services\Communications\MessageDesk;
use App\Services\Verification\VerificationDesk;
use RuntimeException;
use Tests\Feature\Foundation\FoundationTestCase;

class CommunicationsTest extends FoundationTestCase
{
    public function test_registration_and_broker_approval_notify_the_account_holder(): void
    {
        $this->post(route('register'), [
            'name' => 'Amina Buyer',
            'email' => 'amina@example.com',
            'password' => 'password-pass-1',
            'password_confirmation' => 'password-pass-1',
        ])->assertRedirect(route('buyer.dashboard'));

        $buyer = User::query()->where('email', 'amina@example.com')->firstOrFail();
        $welcome = MessageDelivery::query()
            ->where('user_id', $buyer->id)
            ->where('event', MessageEvent::AccountRegistered)
            ->where('channel', MessageChannel::InApp)
            ->first();

        $this->assertNotNull($welcome);
        $this->assertSame('sent', $welcome->status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $buyer->id,
            'type' => TemplatedDeskNotification::class,
        ]);

        $desk = User::factory()->withRoles('staff')->withPermissions('staff.brokerage')->create();
        $application = CapabilityApplication::query()->create([
            'user_id' => $buyer->id,
            'capability' => PartnerCapability::Broker,
            'status' => CapabilityApplicationStatus::Pending,
            'statement' => 'I introduce buyers around Shela.',
        ]);

        app(CapabilityReviewer::class)->decide($desk, $application, CapabilityApplicationStatus::Approved, 'The brokerage desk accepted this application.');

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::BrokerApproved)
                ->where('channel', MessageChannel::InApp)
                ->exists()
        );
    }

    public function test_a_buyer_enquiry_reaches_the_buyer_desk_once_and_keeps_private_text_out(): void
    {
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create(['notify_by_email' => false]);
        $other = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create(['title' => 'Shela ridge']);
        $enquiry = Enquiry::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'message' => 'Meet me at -2.27123456 and open evidence/title-deed.pdf',
        ]);

        EnquirySubmitted::dispatch($enquiry);
        EnquirySubmitted::dispatch($enquiry);

        $rows = MessageDelivery::query()->where('event', MessageEvent::BuyerEnquiry)->get();

        $this->assertCount(1, $rows);
        $this->assertSame($desk->id, $rows->first()->user_id);
        $this->assertFalse($rows->contains('user_id', $other->id));
        $this->assertFalse($rows->contains('user_id', $buyer->id));
        $this->assertStringContainsString('Shela ridge', $rows->first()->body);
        $this->assertStringNotContainsString('2.27123456', $rows->first()->body);
        $this->assertStringNotContainsString('evidence/', $rows->first()->body);
        $this->assertStringNotContainsString('title-deed', $rows->first()->body);
        $this->assertSame(0, MessageDelivery::query()->where('user_id', $desk->id)->where('channel', MessageChannel::Email)->count());
    }

    public function test_property_approval_notifies_the_submitter_and_a_repeat_does_not_send_again(): void
    {
        $broker = User::factory()->withRoles('broker')->create(['notify_by_email' => false]);
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $property = Property::factory()->create([
            'title' => 'Mokowe acres',
            'submitted_by' => $broker->id,
            'restricted_latitude' => -2.27123456,
            'restricted_longitude' => 40.90123456,
        ]);

        $desk = app(VerificationDesk::class);
        $desk->seed($property);

        foreach (VerificationCategory::cases() as $category) {
            if (! $category->critical()) {
                continue;
            }

            $desk->review($property->verificationItems()->where('category', $category)->first(), $staff, [
                'state' => VerificationItemState::NotApplicable->value,
                'evidence_note' => 'Not applicable for this listing.',
            ]);
        }

        app(PropertySubmission::class)->approve($staff, $property);
        app(PropertySubmission::class)->approve($staff, $property->fresh());

        $rows = MessageDelivery::query()
            ->where('user_id', $broker->id)
            ->where('event', MessageEvent::PropertyApproved)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame(MessageChannel::InApp, $rows->first()->channel);
        $this->assertStringContainsString('Mokowe acres', $rows->first()->body);
        $this->assertStringNotContainsString('2.27123456', $rows->first()->body);
        $this->assertStringNotContainsString('40.90123456', $rows->first()->body);
    }

    public function test_unsecured_channels_drop_coordinates_and_private_files(): void
    {
        $user = User::factory()->create([
            'phone' => '0712000111',
            'notify_by_email' => true,
        ]);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'channel' => MessageChannel::Whatsapp,
            'enabled' => true,
        ]);

        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
            'name' => $user->name,
            'title' => 'Shela plot',
            'summary' => 'Pin -2.27123456 and file evidence/title-deed.pdf',
            'restricted_latitude' => '-2.27123456',
            'parcel_identifier' => 'LAMU/BLOCK/9',
            'url' => route('home'),
        ], 'scrub:'.$user->id);

        $deliveries = MessageDelivery::query()->where('user_id', $user->id)->get();

        $this->assertTrue($deliveries->contains('channel', MessageChannel::Whatsapp));

        foreach ($deliveries as $delivery) {
            $stored = $delivery->body.' '.(string) $delivery->destination;
            $this->assertStringNotContainsString('2.27123456', $stored);
            $this->assertStringNotContainsString('evidence/', $stored);
            $this->assertStringNotContainsString('LAMU/BLOCK/9', $stored);
        }

        $link = $deliveries->firstWhere('channel', MessageChannel::Whatsapp);
        $this->assertStringStartsWith('https://wa.me/254712000111?text=', (string) $link->destination);
        $this->assertTrue($user->notifications()->get()->contains(
            fn ($notice): bool => str_contains((string) ($notice->data['message'] ?? ''), 'https://wa.me/254712000111')
        ));
    }

    public function test_a_failed_sms_is_logged_for_retry_without_a_second_message(): void
    {
        $this->app->instance(SmsSender::class, new class implements SmsSender
        {
            public function configured(): bool
            {
                return true;
            }

            public function send(string $phone, string $body): void
            {
                throw new RuntimeException('gateway down');
            }
        });

        $user = User::factory()->create([
            'phone' => '0712000222',
            'notify_by_email' => false,
        ]);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'channel' => MessageChannel::Sms,
            'enabled' => true,
        ]);

        try {
            app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
                'name' => $user->name,
                'title' => 'Welcome',
                'summary' => 'Your account is ready.',
                'url' => route('home'),
            ], 'sms:'.$user->id);
            $this->fail('The SMS gateway failure should surface so the queue can retry.');
        } catch (RuntimeException $exception) {
            $this->assertSame('gateway down', $exception->getMessage());
        }

        $sms = MessageDelivery::query()
            ->where('user_id', $user->id)
            ->where('channel', MessageChannel::Sms)
            ->firstOrFail();

        $this->assertSame('failed', $sms->status);
        $this->assertGreaterThanOrEqual(1, $sms->attempts);
        $this->assertSame(3, (new \App\Jobs\DeliverMessage($sms->idempotency_key))->tries);

        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
            'name' => $user->name,
            'title' => 'Welcome',
            'summary' => 'Your account is ready.',
            'url' => route('home'),
        ], 'sms:'.$user->id);

        $this->assertSame(1, MessageDelivery::query()->where('channel', MessageChannel::Sms)->count());
    }

    public function test_delivery_history_is_limited_to_the_communications_desk(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $properties = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $comms = User::factory()->withRoles('staff')->withPermissions('staff.communications')->create();
        $template = MessageTemplate::query()->where('event', MessageEvent::AccountRegistered)->firstOrFail();

        $this->actingAs($buyer)->get(route('staff.messages.deliveries'))->assertForbidden();
        $this->actingAs($buyer)->get(route('staff.messages.templates'))->assertForbidden();
        $this->actingAs($properties)->get(route('staff.messages.deliveries'))->assertForbidden();

        $this->actingAs($comms)->put(route('staff.messages.templates.update', $template), [
            'subject' => 'Account registration',
            'body' => '{title} {summary}',
            'user_id' => $buyer->id,
        ])->assertRedirect();

        $this->assertSame(0, MessageDelivery::query()->where('user_id', $buyer->id)->count());

        app(MessageDesk::class)->send($buyer, MessageEvent::AccountRegistered, [
            'name' => $buyer->name,
            'title' => 'Welcome to Lamu Acres',
            'summary' => 'Your buyer account is ready.',
            'url' => route('buyer.dashboard'),
        ], 'user:'.$buyer->id);

        $this->actingAs($comms)
            ->get(route('staff.messages.deliveries'))
            ->assertOk()
            ->assertSee('Welcome to Lamu Acres')
            ->assertSee($buyer->name);

        $this->actingAs($buyer)->get(route('staff.messages.deliveries'))->assertForbidden();
    }
}
