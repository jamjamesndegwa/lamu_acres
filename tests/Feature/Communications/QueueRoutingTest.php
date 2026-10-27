<?php

namespace Tests\Feature\Communications;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Jobs\DeliverMessage;
use App\Jobs\DispatchMessageCampaign;
use App\Models\MessageDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Services\Communications\AccountSecurityNotifier;
use App\Services\Communications\CampaignDesk;
use App\Services\Communications\MessageDesk;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Foundation\FoundationTestCase;

class QueueRoutingTest extends FoundationTestCase
{
    use RefreshDatabase;

    public function test_deliver_message_is_dispatched_on_communications_queue_after_commit(): void
    {
        Bus::fake([DeliverMessage::class]);
        Mail::fake();

        $user = User::factory()->create([
            'notify_by_email' => false,
        ]);

        DB::transaction(function () use ($user): void {
            app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
                'name' => $user->name,
                'title' => 'Welcome',
                'summary' => 'Your account is ready.',
                'url' => route('home'),
            ], 'queue:'.$user->id);
        });

        Bus::assertDispatched(DeliverMessage::class, function (DeliverMessage $job): bool {
            return $job->queue === config('communications.queues.communications');
        });
    }

    public function test_mandatory_transactional_email_is_not_blocked_by_notify_by_email_opt_out(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'notify_by_email' => false,
        ]);

        app(MessageDesk::class)->send($user, MessageEvent::OfferStatus, [
            'name' => $user->name,
            'title' => 'Offer update',
            'summary' => 'Your offer moved forward.',
            'url' => route('home'),
        ], 'mandatory:'.$user->id);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $user->id)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
    }

    public function test_marketing_broadcast_email_requires_marketing_consent(): void
    {
        $user = User::factory()->create([
            'notify_by_email' => true,
            'marketing_email_consent' => false,
        ]);

        $allowed = app(MessageDesk::class)->allows($user, MessageChannel::Email, MessageEvent::MarketingBroadcast);

        $this->assertFalse($allowed);
    }

    public function test_campaign_dispatch_uses_campaigns_queue(): void
    {
        Queue::fake();

        $staff = User::factory()->withRoles('staff')->withPermissions('staff.communications')->create();
        $campaign = app(CampaignDesk::class)->create($staff, MessageChannel::Email, 'News', 'Hello');

        app(CampaignDesk::class)->queue($campaign);

        Queue::assertPushed(DispatchMessageCampaign::class, function (DispatchMessageCampaign $job): bool {
            return $job->queue === config('communications.queues.campaigns');
        });
    }

    public function test_password_reset_notification_is_queued_on_critical_queue(): void
    {
        $notification = new QueuedResetPassword('test-token');

        $this->assertSame(config('communications.queues.critical'), $notification->queue);
        $this->assertNotSame(ResetPassword::class, $notification::class);
    }

    public function test_deliver_message_job_is_unique_per_idempotency_key(): void
    {
        $job = new DeliverMessage('event:1:user:1:email');

        $this->assertSame('event:1:user:1:email', $job->uniqueId());
    }

    public function test_mandatory_sms_requires_sms_preference_opt_in(): void
    {
        $user = User::factory()->create(['phone' => '0712000555']);

        $this->assertFalse(
            app(MessageDesk::class)->allows($user, MessageChannel::Sms, MessageEvent::AccountRegistered)
        );

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'channel' => MessageChannel::Sms,
            'enabled' => true,
        ]);

        $this->assertTrue(
            app(MessageDesk::class)->allows($user, MessageChannel::Sms, MessageEvent::AccountRegistered)
        );
    }

    public function test_password_changed_email_uses_critical_queue(): void
    {
        Bus::fake([DeliverMessage::class]);

        $user = User::factory()->create();

        app(AccountSecurityNotifier::class)->passwordChanged($user);

        Bus::assertDispatched(DeliverMessage::class, function (DeliverMessage $job): bool {
            return $job->queue === config('communications.queues.critical');
        });
    }

    public function test_whatsapp_remains_opt_in_for_mandatory_events(): void
    {
        $user = User::factory()->create(['phone' => '0712000555']);

        $this->assertFalse(
            app(MessageDesk::class)->allows($user, MessageChannel::Whatsapp, MessageEvent::AccountRegistered)
        );

        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'channel' => MessageChannel::Whatsapp,
            'enabled' => true,
        ]);

        $this->assertTrue(
            app(MessageDesk::class)->allows($user, MessageChannel::Whatsapp, MessageEvent::AccountRegistered)
        );
    }
}
