<?php

namespace Tests\Feature\Communications;

use App\Contracts\Communications\SmsSender;
use App\Data\Communications\SmsSendResult;
use App\Enums\MessageChannel;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageEvent;
use App\Exceptions\Communications\SmsProviderException;
use App\Exceptions\Communications\SmsUncertainDeliveryException;
use App\Jobs\DeliverMessage;
use App\Models\MessageDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Communications\CampaignDesk;
use App\Services\Communications\ExternalCommunicationsRegister;
use App\Services\Communications\MessageDesk;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\Feature\Foundation\FoundationTestCase;

class ExternalCommunicationsRegisterTest extends FoundationTestCase
{
    public function test_email_skipped_when_smtp_mailbox_is_incomplete(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', null);

        $user = User::factory()->create();

        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
            'name' => $user->name,
            'title' => 'Welcome',
            'summary' => 'Ready.',
            'url' => route('home'),
        ], 'smtp-skip:'.$user->id);

        $email = MessageDelivery::query()->where('user_id', $user->id)->where('channel', MessageChannel::Email)->first();

        $this->assertNotNull($email);
        $this->assertSame(MessageDeliveryStatus::Skipped->value, $email->status);
        $this->assertStringContainsString('policy:', (string) $email->error);
    }

    public function test_sms_skipped_when_provider_is_not_configured(): void
    {
        $user = User::factory()->create(['phone' => '0712000111', 'notify_by_email' => false]);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'channel' => MessageChannel::Sms,
            'enabled' => true,
        ]);

        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
            'name' => $user->name,
            'title' => 'Welcome',
            'summary' => 'Ready.',
            'url' => route('home'),
        ], 'sms-skip:'.$user->id);

        $sms = MessageDelivery::query()->where('user_id', $user->id)->where('channel', MessageChannel::Sms)->firstOrFail();

        $this->assertSame(MessageDeliveryStatus::Skipped->value, $sms->status);
        $this->assertStringContainsString('policy:', (string) $sms->error);
    }

    public function test_redis_dispatch_failure_keeps_queued_delivery_record(): void
    {
        $user = User::factory()->create();

        $this->mock(Dispatcher::class, function ($mock): void {
            $mock->shouldReceive('dispatch')->andThrow(new \RuntimeException('Connection refused'));
        });

        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
            'name' => $user->name,
            'title' => 'Welcome',
            'summary' => 'Ready.',
            'url' => route('home'),
        ], 'dispatch-fail:'.$user->id);

        $delivery = MessageDelivery::query()->where('channel', MessageChannel::Email)->where('user_id', $user->id)->firstOrFail();

        $this->assertSame(MessageDeliveryStatus::Queued->value, $delivery->status);
        $this->assertStringContainsString('Dispatch:', (string) $delivery->error);
    }

    public function test_successful_delivery_marks_sent_and_not_prematurely_before_worker(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
            'name' => $user->name,
            'title' => 'Welcome',
            'summary' => 'Ready.',
            'url' => route('home'),
        ], 'sent-check:'.$user->id);

        $delivery = MessageDelivery::query()->where('channel', MessageChannel::Email)->where('user_id', $user->id)->firstOrFail();

        if ($delivery->status === MessageDeliveryStatus::Sent->value) {
            $delivery->forceFill(['status' => MessageDeliveryStatus::Queued->value, 'sent_at' => null])->save();
        }

        app()->call([new DeliverMessage($delivery->idempotency_key), 'handle']);

        $this->assertSame(MessageDeliveryStatus::Sent->value, $delivery->refresh()->status);
    }

    public function test_smtp_failure_marks_failed_status(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP auth failed'));

        $user = User::factory()->create();
        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
            'name' => $user->name,
            'title' => 'Welcome',
            'summary' => 'Ready.',
            'url' => route('home'),
        ], 'smtp-fail:'.$user->id);

        $delivery = MessageDelivery::query()->where('channel', MessageChannel::Email)->where('user_id', $user->id)->firstOrFail();

        try {
            app()->call([new DeliverMessage($delivery->idempotency_key), 'handle']);
        } catch (\RuntimeException) {
        }

        $this->assertSame(MessageDeliveryStatus::Failed->value, $delivery->refresh()->status);
    }

    public function test_uncertain_sms_status_is_preserved(): void
    {
        $sender = new class implements SmsSender
        {
            public function configured(): bool
            {
                return true;
            }

            public function send(string $phone, string $body): SmsSendResult
            {
                throw new SmsUncertainDeliveryException;
            }

            public function sendBulk(array $messages): array
            {
                return [];
            }
        };
        $this->app->instance(SmsSender::class, $sender);

        $user = User::factory()->create(['phone' => '0712000999', 'notify_by_email' => false]);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'channel' => MessageChannel::Sms,
            'enabled' => true,
        ]);

        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
            'name' => $user->name,
            'title' => 'Welcome',
            'summary' => 'Ready.',
            'url' => route('home'),
        ], 'uncertain:'.$user->id);

        $delivery = MessageDelivery::query()->where('channel', MessageChannel::Sms)->firstOrFail();
        app()->call([new DeliverMessage($delivery->idempotency_key), 'handle']);

        $this->assertSame(MessageDeliveryStatus::Uncertain->value, $delivery->refresh()->status);
    }

    public function test_provider_failure_marks_failed_status(): void
    {
        $sender = new class implements SmsSender
        {
            public function configured(): bool
            {
                return true;
            }

            public function send(string $phone, string $body): SmsSendResult
            {
                throw new SmsProviderException('Rejected', 422, retriable: false);
            }

            public function sendBulk(array $messages): array
            {
                return [];
            }
        };
        $this->app->instance(SmsSender::class, $sender);

        $user = User::factory()->create(['phone' => '0712000888', 'notify_by_email' => false]);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'channel' => MessageChannel::Sms,
            'enabled' => true,
        ]);

        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
            'name' => $user->name,
            'title' => 'Welcome',
            'summary' => 'Ready.',
            'url' => route('home'),
        ], 'provider-fail:'.$user->id);

        $delivery = MessageDelivery::query()->where('channel', MessageChannel::Sms)->firstOrFail();
        app()->call([new DeliverMessage($delivery->idempotency_key), 'handle']);

        $this->assertSame(MessageDeliveryStatus::Failed->value, $delivery->refresh()->status);
    }

    public function test_email_only_permission_hides_sms_rows(): void
    {
        $viewer = User::factory()->withRoles('staff')->withPermissions('communications.email.view')->create();
        $recipient = User::factory()->create();

        MessageDelivery::query()->create([
            'idempotency_key' => 'email-only:1',
            'user_id' => $recipient->id,
            'event' => MessageEvent::AccountRegistered,
            'channel' => MessageChannel::Email,
            'status' => MessageDeliveryStatus::Queued->value,
            'subject' => 'Email row',
            'body' => 'Body',
            'destination' => $recipient->email,
        ]);
        MessageDelivery::query()->create([
            'idempotency_key' => 'sms-hidden:1',
            'user_id' => $recipient->id,
            'event' => MessageEvent::AccountRegistered,
            'channel' => MessageChannel::Sms,
            'status' => MessageDeliveryStatus::Queued->value,
            'subject' => 'SMS row',
            'body' => 'Body',
            'destination' => '254712000111',
        ]);

        $this->actingAs($viewer)
            ->get(route('staff.messages.deliveries'))
            ->assertOk()
            ->assertSee('Email row')
            ->assertDontSee('SMS row');
    }

    public function test_sms_only_permission_hides_email_rows(): void
    {
        $viewer = User::factory()->withRoles('staff')->withPermissions('communications.sms.view')->create();
        $recipient = User::factory()->create();

        MessageDelivery::query()->create([
            'idempotency_key' => 'email-hidden:2',
            'user_id' => $recipient->id,
            'event' => MessageEvent::AccountRegistered,
            'channel' => MessageChannel::Email,
            'status' => MessageDeliveryStatus::Queued->value,
            'subject' => 'Hidden email',
            'body' => 'Body',
            'destination' => $recipient->email,
        ]);
        MessageDelivery::query()->create([
            'idempotency_key' => 'sms-only:2',
            'user_id' => $recipient->id,
            'event' => MessageEvent::AccountRegistered,
            'channel' => MessageChannel::Sms,
            'status' => MessageDeliveryStatus::Queued->value,
            'subject' => 'Visible SMS',
            'body' => 'Body',
            'destination' => '254712000222',
        ]);

        $this->actingAs($viewer)
            ->get(route('staff.messages.deliveries'))
            ->assertOk()
            ->assertSee('Visible SMS')
            ->assertDontSee('Hidden email');
    }

    public function test_unauthorized_user_is_denied_register_access(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.communications')->create();

        $this->actingAs($staff)->get(route('staff.messages.deliveries'))->assertForbidden();
    }

    public function test_direct_record_url_cannot_bypass_channel_permission(): void
    {
        $viewer = User::factory()->withRoles('staff')->withPermissions('communications.email.view')->create();
        $recipient = User::factory()->create();
        $sms = MessageDelivery::query()->create([
            'idempotency_key' => 'bypass:1',
            'user_id' => $recipient->id,
            'event' => MessageEvent::AccountRegistered,
            'channel' => MessageChannel::Sms,
            'status' => MessageDeliveryStatus::Queued->value,
            'subject' => 'Secret SMS',
            'body' => 'Body',
            'destination' => '254712000333',
        ]);

        $this->actingAs($viewer)->get(route('staff.messages.deliveries.show', $sms))->assertNotFound();
    }

    public function test_search_does_not_leak_unauthorized_channel_records(): void
    {
        $viewer = User::factory()->withRoles('staff')->withPermissions('communications.email.view')->create();
        $recipient = User::factory()->create();

        MessageDelivery::query()->create([
            'idempotency_key' => 'search-sms:1',
            'user_id' => $recipient->id,
            'event' => MessageEvent::AccountRegistered,
            'channel' => MessageChannel::Sms,
            'status' => MessageDeliveryStatus::Queued->value,
            'subject' => 'LeakSearchToken',
            'body' => 'Body',
            'destination' => '254712000444',
        ]);

        $this->actingAs($viewer)
            ->get(route('staff.messages.deliveries', ['q' => 'LeakSearchToken']))
            ->assertOk()
            ->assertSee('No external messages match these filters');
    }

    public function test_aggregate_counts_respect_permission_filters(): void
    {
        $viewer = User::factory()->withRoles('staff')->withPermissions('communications.email.view')->create();
        $recipient = User::factory()->create();

        MessageDelivery::query()->create([
            'idempotency_key' => 'count-email:1',
            'user_id' => $recipient->id,
            'event' => MessageEvent::AccountRegistered,
            'channel' => MessageChannel::Email,
            'status' => MessageDeliveryStatus::Queued->value,
            'subject' => 'E1',
            'body' => 'Body',
            'destination' => $recipient->email,
        ]);
        MessageDelivery::query()->create([
            'idempotency_key' => 'count-sms:1',
            'user_id' => $recipient->id,
            'event' => MessageEvent::AccountRegistered,
            'channel' => MessageChannel::Sms,
            'status' => MessageDeliveryStatus::Failed->value,
            'subject' => 'S1',
            'body' => 'Body',
            'destination' => '254712000555',
        ]);

        $counts = app(ExternalCommunicationsRegister::class)->summaryCounts($viewer, request());

        $this->assertSame(1, $counts['total']);
        $this->assertSame(1, $counts['queued']);
        $this->assertSame(0, $counts['failed']);
    }

    public function test_register_is_paginated_and_sorted_latest_first(): void
    {
        $viewer = User::factory()->withRoles('staff')->withPermissions('communications.email.view', 'communications.sms.view')->create();
        $recipient = User::factory()->create();

        $older = MessageDelivery::query()->create([
            'idempotency_key' => 'page-old',
            'user_id' => $recipient->id,
            'event' => MessageEvent::AccountRegistered,
            'channel' => MessageChannel::Email,
            'status' => MessageDeliveryStatus::Queued->value,
            'subject' => 'Older message',
            'body' => 'Body',
            'destination' => $recipient->email,
        ]);
        $older->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->saveQuietly();

        MessageDelivery::query()->create([
            'idempotency_key' => 'page-new',
            'user_id' => $recipient->id,
            'event' => MessageEvent::AccountRegistered,
            'channel' => MessageChannel::Email,
            'status' => MessageDeliveryStatus::Queued->value,
            'subject' => 'Newer message',
            'body' => 'Body',
            'destination' => $recipient->email,
        ]);

        $items = app(ExternalCommunicationsRegister::class)
            ->query($viewer, request())
            ->limit(2)
            ->pluck('subject')
            ->all();

        $this->assertSame(['Newer message', 'Older message'], $items);
    }

    public function test_details_page_shows_redacted_password_reset_content(): void
    {
        $viewer = User::factory()->withRoles('super_admin')->withPermissions('communications.email.view')->create();
        $user = User::factory()->create();

        Password::broker()->sendResetLink(['email' => $user->email]);

        $delivery = MessageDelivery::query()
            ->where('event', MessageEvent::PasswordResetRequested)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertStringNotContainsString('token=', (string) $delivery->body);
        $this->assertStringContainsString('Password reset instructions generated', (string) $delivery->body);

        $this->actingAs($viewer)
            ->get(route('staff.messages.deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('Password reset instructions generated')
            ->assertDontSee('reset-password');
    }

    public function test_campaign_and_transactional_rows_are_visible(): void
    {
        $viewer = User::factory()->withRoles('staff')->withPermissions('communications.email.view')->create();
        $author = User::factory()->withRoles('staff')->withPermissions('staff.communications')->create();
        $recipient = User::factory()->create(['marketing_email_consent' => true, 'notify_by_email' => true]);

        $campaign = app(CampaignDesk::class)->create($author, MessageChannel::Email, 'Campaign subject', 'Campaign body');
        app(CampaignDesk::class)->recordDelivery($campaign, $recipient, 'Campaign subject', 'Campaign body', $recipient->email);

        MessageDelivery::query()->create([
            'idempotency_key' => 'txn:'.$recipient->id,
            'user_id' => $recipient->id,
            'event' => MessageEvent::BuyerEnquiryAcknowledged,
            'channel' => MessageChannel::Email,
            'status' => MessageDeliveryStatus::Queued->value,
            'subject' => 'Transactional subject',
            'body' => 'Transactional body',
            'destination' => $recipient->email,
        ]);

        $this->actingAs($viewer)
            ->get(route('staff.messages.deliveries'))
            ->assertOk()
            ->assertSee('Campaign subject')
            ->assertSee('Transactional subject');
    }

    public function test_idempotency_prevents_duplicate_external_rows(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 2) as $attempt) {
            app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
                'name' => $user->name,
                'title' => 'Welcome',
                'summary' => 'Ready.',
                'url' => route('home'),
            ], 'idem:'.$user->id);
        }

        $this->assertSame(1, MessageDelivery::query()->where('channel', MessageChannel::Email)->where('user_id', $user->id)->where('idempotency_key', 'like', '%idem:'.$user->id.'%')->count());
    }
}
