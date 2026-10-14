<?php

namespace App\Enums;

enum DevelopmentCategory: string
{
    case Refinery = 'refinery';
    case Lapsset = 'lapsset';
    case Port = 'port';
    case Airport = 'airport';
    case Road = 'road';
    case Energy = 'energy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Refinery => 'Refinery and related industry',
            self::Lapsset => 'LAPSSET',
            self::Port => 'Port',
            self::Airport => 'Airport',
            self::Road => 'Road',
            self::Energy => 'Energy',
            self::Other => 'Other documented development',
        };
    }
}
