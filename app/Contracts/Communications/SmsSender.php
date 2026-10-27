<?php

namespace App\Contracts\Communications;

interface SmsSender
{
    public function configured(): bool;

    public function send(string $phone, string $body): string;

    /**
     * @param  array<int, array{phone: string, body: string}>  $messages
     * @return array<string, string> Normalized phone => provider reference
     */
    public function sendBulk(array $messages): array;
}
