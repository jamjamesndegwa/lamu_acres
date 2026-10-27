<?php

namespace Tests\Feature\Communications;

use App\Contracts\Communications\SmsSender;
use App\Data\Communications\SmsSendResult;
use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Exceptions\Communications\SmsUncertainDeliveryException;
use App\Jobs\DeliverMessage;
use App\Models\MessageDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Communications\BulkSmsPreflight;
use App\Services\Communications\HttpSmsSender;
use App\Services\Communications\MailPreflight;
use App\Services\Communications\MessageDesk;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Foundation\FoundationTestCase;

class CommunicationsCertificationTest extends FoundationTestCase
{
    public function test_preflight_reads_profile_balance_and_dry_run_endpoints(): void
    {
        config()->set('services.bulk_sms', [
            'base_url' => 'https://provider.test/api',
            'client_id' => '123',
            'api_key' => 'secret-key',
            'sender_id' => 'LAMUACRES',
            'enabled' => true,
            'dry_run' => false,
        ]);

        Http::fake([
            'https://provider.test/api/123/client/profile' => Http::response([
                'data' => ['name' => 'Lamu Acres', 'sender_ids' => ['LAMUACRES']],
            ], 200),
            'https://provider.test/api/123/client/balance' => Http::response([
                'data' => ['balance' => 42.5, 'currency' => 'KES'],
            ], 200),
            'https://provider.test/api/123/messages/send' => Http::response([
                'data' => ['id' => 'dry-1', 'status' => 'validated', 'dry_run' => true],
            ], 200),
        ]);

        $report = app(BulkSmsPreflight::class)->inspect('0712000123');

        $this->assertTrue($report['configured']);
        $this->assertSame('LAMUACRES', $report['sender_id']);
        $this->assertTrue($report['sender_ready']);
        $this->assertSame(42.5, $report['balance']['balance']);
        $this->assertTrue($report['dry_run_transactional']['ok']);

        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://provider.test/api/123/client/profile'
            && $request->hasHeader('X-API-Key', 'secret-key'));
        Http::assertSent(fn ($request): bool => $request->url() === 'https://provider.test/api/123/client/balance');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://provider.test/api/123/messages/send'
            && $request['dry_run'] === true);
    }

    public function test_preflight_reports_missing_configuration_without_calling_provider(): void
    {
        config()->set('services.bulk_sms.enabled', false);

        $report = app(BulkSmsPreflight::class)->inspect();

        $this->assertFalse($report['configured']);
        Http::assertNothingSent();
    }

    public function test_uncertain_sms_delivery_is_not_retried_and_is_visible_to_staff(): void
    {
        $sender = new class implements SmsSender
        {
            public int $calls = 0;

            public function configured(): bool
            {
                return true;
            }

            public function send(string $phone, string $body): SmsSendResult
            {
                $this->calls++;
                throw new SmsUncertainDeliveryException;
            }

            public function sendBulk(array $messages): array
            {
                return [];
            }
        };
        $this->app->instance(SmsSender::class, $sender);

        $user = User::factory()->create(['phone' => '0712000666', 'notify_by_email' => false]);
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
        app()->call([new DeliverMessage($delivery->idempotency_key), 'handle']);

        $delivery->refresh();
        $this->assertSame('uncertain', $delivery->status);
        $this->assertSame(1, $sender->calls);

        $staff = User::factory()->withRoles('staff')->withPermissions('staff.communications')->create();
        $this->actingAs($staff)
            ->get(route('staff.messages.deliveries'))
            ->assertOk()
            ->assertSee('uncertain (provider outcome unknown)')
            ->assertSee('1 awaiting confirmation');
    }

    public function test_bulk_batch_reference_kind_is_stored_separately_from_message_reference(): void
    {
        config()->set('services.bulk_sms', [
            'base_url' => 'https://provider.test/api',
            'client_id' => '123',
            'api_key' => 'secret-key',
            'sender_id' => 'LAMUACRES',
            'enabled' => true,
            'dry_run' => false,
        ]);

        Http::fake([
            'https://provider.test/api/123/sms/send' => Http::response([
                'data' => [
                    'id' => 'batch-900',
                    'status' => 'accepted',
                    'recipients' => [
                        ['recipient' => '254712345678', 'status' => 'sent', 'message_id' => 'msg-1'],
                    ],
                ],
            ], 200),
        ]);

        $results = app(HttpSmsSender::class)->sendBulk([
            ['phone' => '254712345678', 'body' => 'Shared'],
            ['phone' => '254798765432', 'body' => 'Shared'],
        ]);

        $this->assertSame('message', $results['254712345678']->referenceKind);
        $this->assertSame('msg-1', $results['254712345678']->messageId);
        $this->assertSame('batch', $results['254798765432']->referenceKind);
        $this->assertSame('batch-900', $results['254798765432']->messageId);
    }

    public function test_mail_preflight_reports_smtp_readiness_without_exposing_password(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', 'mail.example.com');
        config()->set('mail.mailers.smtp.port', 587);
        config()->set('mail.mailers.smtp.encryption', 'tls');
        config()->set('mail.mailers.smtp.username', 'notify@example.com');
        config()->set('mail.mailers.smtp.password', 'secret-not-logged');
        config()->set('mail.from.address', 'notify@example.com');

        $report = app(MailPreflight::class)->inspect();

        $this->assertTrue($report['smtp_ready']);
        $this->assertSame('notify@example.com', $report['from_address']);
        $this->assertStringNotContainsString('secret-not-logged', json_encode($report));
    }
}
