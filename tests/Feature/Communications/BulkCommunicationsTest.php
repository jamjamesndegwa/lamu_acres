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
        config()->set('services.bulk_sms', [
            'base_url' => 'https://provider.test/api',
            'client_id' => '123',
            'api_key' => 'secret-key',
            'sender_id' => 'LAMUACRES',
            'enabled' => true,
            'dry_run' => false,
        ]);

        Http::fake([
            'https://provider.test/api/123/messages/send' => Http::response([
                'data' => ['id' => 'sms-ref-1', 'status' => 'sent'],
            ], 200),
        ]);

        $staff = User::factory()->withRoles('staff')->withPermissions('staff.communications')->create();
        $optedIn = User::factory()->withRoles('buyer')->create([
            'phone' => '0712000333',
            'marketing_sms_consent' => true,
        ]);
        User::factory()->create([
            'phone' => '0712000444',
            'marketing_sms_consent' => false,
        ]);

        $campaign = app(CampaignDesk::class)->create($staff, MessageChannel::Sms, 'New plots', 'Visit Lamu Acres this week.');
        app(CampaignDesk::class)->syncRecipients($campaign, [$optedIn->id]);
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
        $buyer = User::factory()->withRoles('buyer')->create([
            'notify_by_email' => true,
            'marketing_email_consent' => true,
        ]);

        $campaign = app(CampaignDesk::class)->create($staff, MessageChannel::Email, 'Weekly digest', 'Fresh listings are live.');
        app(CampaignDesk::class)->syncRecipients($campaign, [$buyer->id]);
        app(CampaignDesk::class)->queue($campaign);
        DispatchMessageCampaign::dispatchSync($campaign->id);

        Mail::assertSentCount(1);
        $this->assertSame(1, MessageDelivery::query()->where('user_id', $buyer->id)->count());

        DispatchMessageCampaign::dispatchSync($campaign->id);
        $this->assertSame(1, MessageDelivery::query()->where('user_id', $buyer->id)->count());
    }

    public function test_staff_select_recipients_on_draft_campaign_before_queueing(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.communications')->create();
        $included = User::factory()->withRoles('buyer')->create([
            'notify_by_email' => true,
            'marketing_email_consent' => true,
        ]);
        User::factory()->create([
            'notify_by_email' => true,
            'marketing_email_consent' => true,
        ]);

        $campaign = app(CampaignDesk::class)->create($staff, MessageChannel::Email, 'Selected only', 'Hello selected buyers.');

        $this->actingAs($staff)
            ->get(route('staff.messages.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Select recipients')
            ->assertSee($included->email);

        $this->actingAs($staff)
            ->get(route('staff.messages.campaigns.create'))
            ->assertOk()
            ->assertSee('Select recipients');

        $this->actingAs($staff)
            ->put(route('staff.messages.campaigns.recipients.update', $campaign), [
                'recipient_ids' => [$included->id],
            ])
            ->assertRedirect(route('staff.messages.campaigns.show', $campaign));

        $this->assertSame(1, $campaign->fresh()->recipients()->count());

        $this->actingAs($staff)
            ->post(route('staff.messages.campaigns.queue', $campaign))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $campaign->fresh()->recipient_count);
    }

    public function test_queueing_without_recipients_is_rejected(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.communications')->create();
        $campaign = app(CampaignDesk::class)->create($staff, MessageChannel::Email, 'Nobody', 'Hello');

        $this->actingAs($staff)
            ->post(route('staff.messages.campaigns.queue', $campaign))
            ->assertSessionHasErrors('recipient_ids');
    }

    public function test_campaign_audience_excludes_staff_and_requires_marketing_consent(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.communications')->create();
        User::factory()->withRoles('buyer')->create([
            'marketing_email_consent' => false,
            'notify_by_email' => true,
        ]);
        $optedIn = User::factory()->withRoles('buyer')->create([
            'marketing_email_consent' => true,
            'notify_by_email' => true,
        ]);
        User::factory()->withRoles('super_admin')->create([
            'marketing_email_consent' => true,
            'notify_by_email' => true,
        ]);

        $audience = app(CampaignDesk::class)->audienceForSelection(MessageChannel::Email);

        $this->assertSame([$optedIn->id], $audience->pluck('id')->all());

        $this->actingAs($staff)
            ->get(route('staff.messages.campaigns.create'))
            ->assertOk()
            ->assertSee($optedIn->email)
            ->assertDontSee('admin@lamuacres.test');
    }

    public function test_registration_can_store_marketing_consent(): void
    {
        $this->post(route('register'), [
            'name' => 'Consent Buyer',
            'email' => 'consent-buyer@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'marketing_email_consent' => '1',
        ])->assertRedirect(route('buyer.dashboard'));

        $user = User::query()->where('email', 'consent-buyer@example.test')->firstOrFail();
        $this->assertTrue($user->marketing_email_consent);
        $this->assertTrue($user->notify_by_email);
        $this->assertTrue($user->hasRole('buyer'));
    }
}
