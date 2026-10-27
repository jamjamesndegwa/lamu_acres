<?php

namespace App\Contracts\Communications;

use App\Data\Communications\SmsBulkRecipientResult;
use App\Data\Communications\SmsSendResult;

interface SmsSender
{
    public function configured(): bool;

    public function send(string $phone, string $body): SmsSendResult;

    /**
     * @param  array<int, array{phone: string, body: string}>  $messages
     * @return array<string, SmsBulkRecipientResult> Normalized phone => outcome
     */
    public function sendBulk(array $messages): array;
}
