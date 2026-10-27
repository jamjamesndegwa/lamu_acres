<?php

namespace Tests\Feature\Communications;

use App\Enums\MessageCampaignStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Jobs\DispatchMessageCampaign;
use App\Models\MessageCampaign;
use App\Models\MessageDelivery;
use App\Models\User;
use App\Services\Communications\CampaignDesk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Foundation\FoundationTestCase;

class BulkCommunicationsTest extends FoundationTestCase
{
    use RefreshDatabase;

    public function test_marketing_sms_campaign_respects_consent_and_records_provider_reference(): void
    {
        Mail::fake();
        config()->set('services.sms.endpoint', 'https://sms.test/send');
        config()->set('services.sms.bulk_endpoint', 'https://sms.test/bulk');
        config()->set('services.sms.token', 'secret');
        config()->set('services.sms.tenant_id', 'tenant-1');
        config()->set('services.sms.from', 'LamuAcres');

        Http::fake([
            'https://sms.test/bulk' => Http::response([
                'messages' => [
                    ['to' => '254712000333', 'reference' => 'sms-ref-1'],
                ],
            ], 200),
        ]);

        $staff = User::factory()->withRoles('staff')->withPermissions('staff.communications')->create();
        $optedIn = User::factory()->create([
            'phone' => '0712000333',
            'marketing_sms_consent' => true,
        ]);
        User::factory()->create([
            'phone' => '0712000444',
            'marketing_sms_consent' => false,
        ]);

        $campaign = app(CampaignDesk::class)->create($staff, MessageChannel::Sms, 'New plots', 'Visit Lamu Acres this week.');
        app(CampaignDesk::class)->queue($campaign);

        DispatchMessageCampaign::dispatchSync($campaign->id);

        $campaign->refresh();
        $this->assertSame(MessageCampaignStatus::Completed, $campaign->status);
        $this->assertSame(1, $campaign->sent_count);

        $delivery = MessageDelivery::query()
            ->where('user_id', $optedIn->id)
            ->where('event', MessageEvent::MarketingBroadcast)
            ->firstOrFail();

        $this->assertSame('sent', $delivery->status);
        $this->assertSame('sms-ref-1', $delivery->provider_reference);
        $this->assertStringContainsString('Unsubscribe:', $delivery->body);
        $this->assertSame(1, MessageDelivery::query()->where('event', MessageEvent::MarketingBroadcast)->count());
    }

    public function test_unsubscribe_link_clears_marketing_consent(): void
    {
        $user = User::factory()->create([
            'marketing_email_consent' => true,
            'marketing_sms_consent' => true,
        ]);

        $this->get(route('communications.unsubscribe', $user->communications_unsubscribe_token))
            ->assertOk()
            ->assertSee('Marketing preferences');

        $this->post(route('communications.unsubscribe.update', $user->communications_unsubscribe_token))
            ->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->marketing_email_consent);
        $this->assertFalse($user->marketing_sms_consent);
    }

    public function test_staff_without_communications_permission_cannot_queue_campaigns(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $campaign = MessageCampaign::query()->create([
            'created_by' => $staff->id,
            'channel' => MessageChannel::Email,
            'subject' => 'News',
            'body' => 'Hello',
            'status' => MessageCampaignStatus::Draft,
        ]);

        $this->actingAs($staff)
            ->post(route('staff.messages.campaigns.queue', $campaign))
            ->assertForbidden();
    }

    public function test_email_campaign_uses_mail_transport_without_duplicates(): void
    {
        Mail::fake();

        $staff = User::factory()->withRoles('staff')->withPermissions('staff.communications')->create();
        $buyer = User::factory()->create([
            'notify_by_email' => true,
            'marketing_email_consent' => true,
        ]);

        $campaign = app(CampaignDesk::class)->create($staff, MessageChannel::Email, 'Weekly digest', 'Fresh listings are live.');
        app(CampaignDesk::class)->queue($campaign);
        DispatchMessageCampaign::dispatchSync($campaign->id);

        Mail::assertSentCount(1);
        $this->assertSame(1, MessageDelivery::query()->where('user_id', $buyer->id)->count());

        DispatchMessageCampaign::dispatchSync($campaign->id);
        $this->assertSame(1, MessageDelivery::query()->where('user_id', $buyer->id)->count());
    }
}
