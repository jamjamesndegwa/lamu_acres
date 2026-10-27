<?php

namespace App\Services\Communications;

use App\Contracts\Communications\SmsSender;
use App\Data\Communications\SmsBulkRecipientResult;
use App\Data\Communications\SmsSendResult;
use App\Exceptions\Communications\SmsProviderException;
use App\Exceptions\Communications\SmsUncertainDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class HttpSmsSender implements SmsSender
{
    private const int BULK_RECIPIENT_LIMIT = 1000;

    public function configured(): bool
    {
        return (bool) config('services.bulk_sms.enabled')
            && filled(config('services.bulk_sms.base_url'))
            && filled(config('services.bulk_sms.client_id'))
            && filled(config('services.bulk_sms.api_key'));
    }

    public function send(string $phone, string $body): SmsSendResult
    {
        $recipient = $this->normalizePhone($phone);

        if ($recipient === '') {
            throw new RuntimeException('SMS recipient is invalid.');
        }

        $clientId = $this->clientId();
        $payload = [
            'client_id' => $clientId,
            'channel' => 'sms',
            'recipient' => $recipient,
            'sender' => (string) config('services.bulk_sms.sender_id'),
            'body' => $body,
            'dry_run' => (bool) config('services.bulk_sms.dry_run'),
        ];

        $response = $this->post($this->singleSendUrl($clientId), $payload);

        return $this->parseSingleResponse($response);
    }

    public function sendBulk(array $messages): array
    {
        if ($messages === []) {
            return [];
        }

        $grouped = [];

        foreach ($messages as $message) {
            $phone = $this->normalizePhone($message['phone']);
            $body = $message['body'];

            if ($phone === '') {
                continue;
            }

            $grouped[$body][] = $phone;
        }

        $results = [];

        foreach ($grouped as $body => $phones) {
            $phones = array_values(array_unique($phones));

            if (count($phones) === 1) {
                try {
                    $single = $this->send($phones[0], $body);
                    $results[$phones[0]] = new SmsBulkRecipientResult(
                        success: true,
                        messageId: $single->messageId,
                        status: $single->status,
                        cost: $single->cost,
                        segments: $single->segments,
                    );
                } catch (SmsUncertainDeliveryException $exception) {
                    $results[$phones[0]] = new SmsBulkRecipientResult(
                        success: false,
                        uncertain: true,
                        error: class_basename($exception).': outcome unknown.',
                    );
                } catch (SmsProviderException $exception) {
                    $results[$phones[0]] = new SmsBulkRecipientResult(
                        success: false,
                        error: class_basename($exception).': delivery failed.',
                    );

                    if ($exception instanceof SmsProviderException && ! $exception->retriable) {
                        throw $exception;
                    }
                }

                continue;
            }

            foreach (array_chunk($phones, self::BULK_RECIPIENT_LIMIT) as $chunk) {
                $chunkResults = $this->sendBulkChunk($chunk, $body);
                $results += $chunkResults;
            }
        }

        foreach ($messages as $message) {
            $phone = $this->normalizePhone($message['phone']);

            if ($phone !== '' && ! array_key_exists($phone, $results)) {
                $results[$phone] = new SmsBulkRecipientResult(success: false, error: 'SMS provider did not return a recipient outcome.');
            }
        }

        return $results;
    }

    /**
     * @param  array<int, string>  $recipients
     * @return array<string, SmsBulkRecipientResult>
     */
    private function sendBulkChunk(array $recipients, string $message): array
    {
        $clientId = $this->clientId();
        $payload = [
            'recipients' => $recipients,
            'message' => $message,
            'sender_id' => (string) config('services.bulk_sms.sender_id'),
            'schedule_time' => null,
        ];

        try {
            $response = $this->post($this->bulkSendUrl($clientId), $payload);
        } catch (SmsUncertainDeliveryException) {
            return array_fill_keys($recipients, new SmsBulkRecipientResult(
                success: false,
                uncertain: true,
                error: 'SmsUncertainDeliveryException: outcome unknown.',
            ));
        }

        return $this->parseBulkResponse($response, $recipients);
    }

    private function post(string $url, array $payload): Response
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => (string) config('services.bulk_sms.api_key'),
            ])
                ->acceptJson()
                ->timeout(20)
                ->post($url, $payload);
        } catch (ConnectionException) {
            $this->logFailure('timeout', 0);

            throw new SmsUncertainDeliveryException;
        }

        if ($response->successful()) {
            return $response;
        }

        $this->handleErrorResponse($response);

        return $response;
    }

    private function handleErrorResponse(Response $response): void
    {
        $status = $response->status();
        $this->logFailure('http', $status);

        match (true) {
            $status === 401 => throw new SmsProviderException('SMS credentials are invalid.', $status),
            $status === 402 => throw new SmsProviderException('SMS account balance is insufficient.', $status),
            $status === 422 => throw new SmsProviderException('SMS request was rejected by the provider.', $status),
            $status === 429 => throw new SmsProviderException('SMS provider rate limit reached.', $status, retriable: true),
            $status >= 500 => throw new SmsUncertainDeliveryException('SMS provider failure.'),
            default => throw new SmsProviderException('SMS provider returned '.$status.'.', $status),
        };
    }

    private function parseSingleResponse(Response $response): SmsSendResult
    {
        $data = $this->data($response->json());

        $messageId = (string) ($data['message_id'] ?? $data['id'] ?? $data['reference'] ?? '');

        if ($messageId === '') {
            throw new RuntimeException('SMS provider did not return a message id.');
        }

        return new SmsSendResult(
            messageId: $messageId,
            status: isset($data['status']) ? (string) $data['status'] : null,
            cost: isset($data['cost']) ? (float) $data['cost'] : null,
            segments: isset($data['segments']) ? (int) $data['segments'] : null,
            balance: isset($data['balance']) ? (float) $data['balance'] : null,
            dryRun: (bool) ($data['dry_run'] ?? config('services.bulk_sms.dry_run')),
        );
    }

    /**
     * @param  array<int, string>  $recipients
     * @return array<string, SmsBulkRecipientResult>
     */
    private function parseBulkResponse(Response $response, array $recipients): array
    {
        $data = $this->data($response->json());
        $results = [];
        $rows = $data['recipients'] ?? $data['results'] ?? null;

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $phone = $this->normalizePhone((string) ($row['recipient'] ?? $row['to'] ?? ''));
                $status = isset($row['status']) ? strtolower((string) $row['status']) : null;
                $messageId = (string) ($row['message_id'] ?? $row['id'] ?? $row['reference'] ?? '');
                $failedStatuses = ['failed', 'rejected', 'error', 'undelivered'];
                $success = ($row['success'] ?? null) === true
                    || in_array($status, ['sent', 'queued', 'delivered', 'success', 'accepted'], true)
                    || ($messageId !== '' && ! in_array($status, $failedStatuses, true));
                $error = (string) ($row['error'] ?? $row['message'] ?? '');

                if ($phone === '') {
                    continue;
                }

                $results[$phone] = new SmsBulkRecipientResult(
                    success: $success,
                    messageId: $messageId !== '' ? $messageId : null,
                    status: $status,
                    error: $success ? null : ($error !== '' ? $error : 'SMS delivery failed.'),
                    cost: isset($row['cost']) ? (float) $row['cost'] : null,
                    segments: isset($row['segments']) ? (int) $row['segments'] : null,
                    referenceKind: 'message',
                );
            }
        }

        $batchId = (string) ($data['message_id'] ?? $data['batch_id'] ?? $data['id'] ?? '');
        $batchStatus = isset($data['status']) ? strtolower((string) $data['status']) : null;
        $batchSuccess = in_array($batchStatus, ['sent', 'queued', 'delivered', 'success', 'accepted'], true);

        foreach ($recipients as $recipient) {
            if (isset($results[$recipient])) {
                continue;
            }

            if ($batchId !== '' && $batchSuccess) {
                $results[$recipient] = new SmsBulkRecipientResult(
                    success: true,
                    messageId: $batchId,
                    status: $batchStatus,
                    referenceKind: 'batch',
                );

                continue;
            }

            $results[$recipient] = new SmsBulkRecipientResult(
                success: false,
                error: 'SMS provider did not return a recipient outcome.',
            );
        }

        return $results;
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

    private function singleSendUrl(int $clientId): string
    {
        return rtrim((string) config('services.bulk_sms.base_url'), '/').'/'.$clientId.'/messages/send';
    }

    private function bulkSendUrl(int $clientId): string
    {
        return rtrim((string) config('services.bulk_sms.base_url'), '/').'/'.$clientId.'/sms/send';
    }

    private function clientId(): int
    {
        return (int) config('services.bulk_sms.client_id');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = '254'.substr($digits, 1);
        }

        return $digits;
    }

    private function logFailure(string $kind, int $status): void
    {
        Log::warning('Bulk SMS provider request failed.', [
            'kind' => $kind,
            'status' => $status > 0 ? $status : null,
        ]);
    }
}
