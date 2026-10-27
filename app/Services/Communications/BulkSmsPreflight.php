<?php

namespace App\Services\Communications;

use App\Exceptions\Communications\SmsProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BulkSmsPreflight
{
    public function __construct(private HttpSmsSender $sender) {}

    public function configured(): bool
    {
        return $this->sender->configured();
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(?string $dryRunRecipient = null): array
    {
        if (! $this->configured()) {
            return [
                'configured' => false,
                'detail' => 'Bulk SMS is disabled or required settings are missing.',
            ];
        }

        $report = [
            'configured' => true,
            'base_url_set' => filled(config('services.bulk_sms.base_url')),
            'client_id_set' => filled(config('services.bulk_sms.client_id')),
            'sender_id' => (string) config('services.bulk_sms.sender_id'),
            'dry_run_mode' => (bool) config('services.bulk_sms.dry_run'),
            'profile' => null,
            'balance' => null,
            'sender_ready' => null,
            'dry_run_transactional' => null,
        ];

        try {
            $report['profile'] = $this->profileSummary($this->get($this->profileUrl()));
            $report['balance'] = $this->balanceSummary($this->get($this->balanceUrl()));
            $report['sender_ready'] = $this->senderReadiness($report['profile'], $report['sender_id']);
        } catch (Throwable $exception) {
            Log::warning('Bulk SMS pre-flight failed.', [
                'reason' => class_basename($exception),
            ]);
            $report['connectivity'] = 'failed';
            $report['detail'] = 'Provider profile or balance could not be read.';
        }

        if ($dryRunRecipient !== null && filled($dryRunRecipient)) {
            $report['dry_run_transactional'] = $this->dryRunTransactional($dryRunRecipient);
        }

        return $report;
    }

    public function dryRunTransactional(string $phone): array
    {
        $originalDryRun = (bool) config('services.bulk_sms.dry_run');
        config(['services.bulk_sms.dry_run' => true]);

        try {
            $result = $this->sender->send($phone, 'Lamu Acres connectivity check (dry run).');

            return [
                'ok' => true,
                'message_id' => $result->messageId,
                'status' => $result->status,
                'segments' => $result->segments,
                'cost' => $result->cost,
                'dry_run' => $result->dryRun,
            ];
        } catch (Throwable $exception) {
            return [
                'ok' => false,
                'reason' => class_basename($exception),
            ];
        } finally {
            config(['services.bulk_sms.dry_run' => $originalDryRun]);
        }
    }

    private function get(string $url): Response
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => (string) config('services.bulk_sms.api_key'),
            ])
                ->acceptJson()
                ->timeout(15)
                ->get($url);
        } catch (ConnectionException) {
            throw new SmsProviderException('SMS provider timed out.', 0);
        }

        if ($response->status() === 401) {
            throw new SmsProviderException('SMS credentials are invalid.', 401);
        }

        if ($response->failed()) {
            throw new SmsProviderException('SMS provider returned '.$response->status().'.', $response->status());
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function profileSummary(Response $response): array
    {
        $data = $this->data($response->json());

        return array_filter([
            'client_name' => $data['name'] ?? $data['client_name'] ?? null,
            'status' => $data['status'] ?? null,
            'sender_ids' => is_array($data['sender_ids'] ?? null) ? array_values($data['sender_ids']) : null,
            'registered_senders' => is_array($data['senders'] ?? null) ? array_values($data['senders']) : null,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function balanceSummary(Response $response): array
    {
        $data = $this->data($response->json());

        return array_filter([
            'balance' => isset($data['balance']) ? (float) $data['balance'] : (isset($data['amount']) ? (float) $data['amount'] : null),
            'currency' => $data['currency'] ?? null,
            'units' => $data['units'] ?? null,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>|null  $profile
     */
    private function senderReadiness(?array $profile, string $senderId): ?bool
    {
        if ($profile === null || $senderId === '') {
            return null;
        }

        $lists = [
            $profile['sender_ids'] ?? [],
            $profile['registered_senders'] ?? [],
        ];

        foreach ($lists as $list) {
            if (! is_array($list) || $list === []) {
                continue;
            }

            return in_array($senderId, $list, true);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function data(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $data = $payload['data'] ?? $payload;

        return is_array($data) ? $data : [];
    }

    private function profileUrl(): string
    {
        return $this->clientUrl('client/profile');
    }

    private function balanceUrl(): string
    {
        return $this->clientUrl('client/balance');
    }

    private function clientUrl(string $suffix): string
    {
        $clientId = (int) config('services.bulk_sms.client_id');

        return rtrim((string) config('services.bulk_sms.base_url'), '/').'/'.$clientId.'/'.$suffix;
    }
}
