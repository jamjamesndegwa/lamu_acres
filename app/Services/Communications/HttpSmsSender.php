<?php

namespace App\Services\Communications;

use App\Contracts\Communications\SmsSender;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSmsSender implements SmsSender
{
    public function configured(): bool
    {
        return filled(config('services.sms.endpoint'))
            && filled(config('services.sms.token'))
            && filled(config('services.sms.tenant_id'));
    }

    public function send(string $phone, string $body): string
    {
        $references = $this->sendBulk([['phone' => $phone, 'body' => $body]]);

        return $references[$this->normalizePhone($phone)] ?? throw new RuntimeException('SMS provider did not return a reference.');
    }

    public function sendBulk(array $messages): array
    {
        if ($messages === []) {
            return [];
        }

        $payload = [
            'tenant_id' => (string) config('services.sms.tenant_id'),
            'from' => (string) config('services.sms.from'),
            'messages' => array_map(fn (array $message): array => [
                'to' => $this->normalizePhone($message['phone']),
                'body' => $message['body'],
            ], $messages),
        ];

        $response = $this->request((string) config('services.sms.bulk_endpoint', config('services.sms.endpoint')), $payload);

        return $this->parseReferences($response, $messages);
    }

    private function request(string $endpoint, array $payload): Response
    {
        $response = Http::withToken((string) config('services.sms.token'))
            ->acceptJson()
            ->timeout(20)
            ->post($endpoint, $payload);

        if ($response->failed()) {
            throw new RuntimeException('SMS provider returned '.$response->status().'.');
        }

        return $response;
    }

    /**
     * @param  array<int, array{phone: string, body: string}>  $messages
     * @return array<string, string>
     */
    private function parseReferences(Response $response, array $messages): array
    {
        $data = $response->json();
        $references = [];

        if (is_array($data) && isset($data['messages']) && is_array($data['messages'])) {
            foreach ($data['messages'] as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $phone = $this->normalizePhone((string) ($row['to'] ?? $messages[$index]['phone'] ?? ''));
                $reference = (string) ($row['reference'] ?? $row['message_id'] ?? $row['id'] ?? '');

                if ($phone !== '' && $reference !== '') {
                    $references[$phone] = $reference;
                }
            }
        }

        $fallback = (string) (is_array($data) ? ($data['reference'] ?? $data['message_id'] ?? $data['id'] ?? '') : '');

        foreach ($messages as $message) {
            $phone = $this->normalizePhone($message['phone']);

            if ($phone === '' || isset($references[$phone])) {
                continue;
            }

            if ($fallback !== '') {
                $references[$phone] = $fallback;
            }
        }

        if ($references === [] && $fallback !== '' && count($messages) === 1) {
            $references[$this->normalizePhone($messages[0]['phone'])] = $fallback;
        }

        return $references;
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
}
