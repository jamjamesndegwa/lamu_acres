<?php

namespace App\Services\Communications;

use App\Contracts\Communications\SmsSender;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSmsSender implements SmsSender
{
    public function configured(): bool
    {
        return filled(config('services.sms.endpoint')) && filled(config('services.sms.token'));
    }

    public function send(string $phone, string $body): void
    {
        $response = Http::withToken((string) config('services.sms.token'))
            ->acceptJson()
            ->timeout(10)
            ->post((string) config('services.sms.endpoint'), [
                'to' => $phone,
                'from' => (string) config('services.sms.from'),
                'body' => $body,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('SMS provider returned '.$response->status().'.');
        }
    }
}
