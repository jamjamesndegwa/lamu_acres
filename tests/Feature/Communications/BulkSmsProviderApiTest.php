<?php

namespace Tests\Feature\Communications;

use App\Data\Communications\SmsBulkRecipientResult;
use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Exceptions\Communications\SmsProviderException;
use App\Jobs\DeliverMessage;
use App\Models\MessageDelivery;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Communications\HttpSmsSender;
use App\Services\Communications\MessageDesk;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Foundation\FoundationTestCase;

class BulkSmsProviderApiTest extends FoundationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.bulk_sms', [
            'base_url' => 'https://provider.test/api',
            'client_id' => '123',
            'api_key' => 'secret-key',
            'sender_id' => 'LAMUACRES',
            'enabled' => true,
            'dry_run' => false,
        ]);
    }

    public function test_single_sms_uses_provider_url_headers_and_payload(): void
    {
        Http::fake([
            'https://provider.test/api/123/messages/send' => Http::response([
                'data' => [
                    'id' => 'msg-100',
                    'status' => 'queued',
                    'cost' => 0.8,
                    'segments' => 1,
                    'balance' => 120.5,
                ],
            ], 200),
        ]);

        $result = app(HttpSmsSender::class)->send('0712345678', 'Your notification');

        $this->assertSame('msg-100', $result->messageId);
        $this->assertSame('queued', $result->status);
        $this->assertSame(0.8, $result->cost);
        $this->assertSame(1, $result->segments);
        $this->assertSame(120.5, $result->balance);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://provider.test/api/123/messages/send'
                && $request->hasHeader('X-API-Key', 'secret-key')
                && $request['client_id'] === 123
                && $request['channel'] === 'sms'
                && $request['recipient'] === '254712345678'
                && $request['sender'] === 'LAMUACRES'
                && $request['body'] === 'Your notification'
                && $request['dry_run'] === false;
        });
    }

    public function test_single_sms_dry_run_flag_is_sent_when_enabled(): void
    {
        config()->set('services.bulk_sms.dry_run', true);

        Http::fake([
            'https://provider.test/api/123/messages/send' => Http::response([
                'data' => ['id' => 'dry-1', 'dry_run' => true, 'status' => 'validated'],
            ], 200),
        ]);

        $result = app(HttpSmsSender::class)->send('254712345678', 'Dry run');

        $this->assertTrue($result->dryRun);
        Http::assertSent(fn ($request): bool => $request['dry_run'] === true);
    }

    public function test_bulk_sms_uses_provider_bulk_endpoint_and_payload(): void
    {
        Http::fake([
            'https://provider.test/api/123/sms/send' => Http::response([
                'data' => [
                    'recipients' => [
                        ['recipient' => '254712345678', 'status' => 'sent', 'message_id' => 'bulk-a'],
                        ['recipient' => '254798765432', 'status' => 'sent', 'message_id' => 'bulk-b'],
                    ],
                ],
            ], 200),
        ]);

        $results = app(HttpSmsSender::class)->sendBulk([
            ['phone' => '0712345678', 'body' => 'Your announcement'],
            ['phone' => '0798765432', 'body' => 'Your announcement'],
        ]);

        $this->assertInstanceOf(SmsBulkRecipientResult::class, $results['254712345678']);
        $this->assertTrue($results['254712345678']->success);
        $this->assertSame('bulk-a', $results['254712345678']->messageId);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://provider.test/api/123/sms/send'
                && $request['recipients'] === ['254712345678', '254798765432']
                && $request['message'] === 'Your announcement'
                && $request['sender_id'] === 'LAMUACRES'
                && $request['schedule_time'] === null;
        });
    }

    public function test_bulk_sms_records_partial_failures_per_recipient(): void
    {
        Http::fake([
            'https://provider.test/api/123/sms/send' => Http::response([
                'data' => [
                    'recipients' => [
                        ['recipient' => '254712345678', 'status' => 'sent', 'message_id' => 'ok-1'],
                        ['recipient' => '254798765432', 'status' => 'failed', 'error' => 'invalid number'],
                    ],
                ],
            ], 200),
        ]);

        $results = app(HttpSmsSender::class)->sendBulk([
            ['phone' => '254712345678', 'body' => 'Shared'],
            ['phone' => '254798765432', 'body' => 'Shared'],
        ]);

        $this->assertTrue($results['254712345678']->success);
        $this->assertFalse($results['254798765432']->success);
        $this->assertSame('invalid number', $results['254798765432']->error);
    }

    public function test_provider_authentication_errors_are_mapped(): void
    {
        Http::fake([
            'https://provider.test/api/123/messages/send' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(SmsProviderException::class);
        $this->expectExceptionMessage('credentials');

        app(HttpSmsSender::class)->send('254712345678', 'Hello');
    }

    public function test_provider_insufficient_balance_errors_are_mapped(): void
    {
        Http::fake([
            'https://provider.test/api/123/messages/send' => Http::response(['message' => 'Payment required'], 402),
        ]);

        try {
            app(HttpSmsSender::class)->send('254712345678', 'Hello');
            $this->fail('Expected insufficient balance exception.');
        } catch (SmsProviderException $exception) {
            $this->assertSame(402, $exception->statusCode);
            $this->assertStringContainsString('balance', strtolower($exception->getMessage()));
        }
    }

    public function test_transactional_delivery_stores_provider_reference_and_meta(): void
    {
        Http::fake([
            'https://provider.test/api/123/messages/send' => Http::response([
                'data' => ['id' => 'txn-55', 'status' => 'sent', 'segments' => 2],
            ], 200),
        ]);

        $user = User::factory()->create([
            'phone' => '0712000999',
            'notify_by_email' => false,
        ]);
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
        ], 'provider:'.$user->id);

        $delivery = MessageDelivery::query()
            ->where('user_id', $user->id)
            ->where('channel', MessageChannel::Sms)
            ->firstOrFail();

        app()->call([new DeliverMessage($delivery->idempotency_key), 'handle']);

        $delivery->refresh();
        $this->assertSame('sent', $delivery->status);
        $this->assertSame('txn-55', $delivery->provider_reference);
        $this->assertSame('sent', $delivery->provider_meta['status'] ?? null);
        $this->assertSame(2, $delivery->provider_meta['segments'] ?? null);
    }

    public function test_duplicate_transactional_sms_is_not_sent_twice(): void
    {
        Http::fake([
            'https://provider.test/api/123/messages/send' => Http::response([
                'data' => ['id' => 'txn-dup'],
            ], 200),
        ]);

        $user = User::factory()->create(['phone' => '0712000888', 'notify_by_email' => false]);
        NotificationPreference::query()->create([
            'user_id' => $user->id,
            'channel' => MessageChannel::Sms,
            'enabled' => true,
        ]);

        $context = [
            'name' => $user->name,
            'title' => 'Welcome',
            'summary' => 'Ready.',
            'url' => route('home'),
        ];

        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, $context, 'dup:'.$user->id);
        $delivery = MessageDelivery::query()->where('channel', MessageChannel::Sms)->firstOrFail();
        app()->call([new DeliverMessage($delivery->idempotency_key), 'handle']);

        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, $context, 'dup:'.$user->id);

        $this->assertSame(1, MessageDelivery::query()->where('channel', MessageChannel::Sms)->count());
        Http::assertSentCount(1);
    }
}
