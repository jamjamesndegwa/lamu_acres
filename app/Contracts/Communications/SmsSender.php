<?php

namespace App\Contracts\Communications;

interface SmsSender
{
    public function configured(): bool;

    public function send(string $phone, string $body): void;
}
