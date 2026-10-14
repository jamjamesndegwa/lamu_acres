<?php

namespace App\Enums;

enum OperationsStatus: string
{
    case Configured = 'configured';
    case Healthy = 'healthy';
    case Unhealthy = 'unhealthy';
    case NotConfigured = 'not_configured';

    public function label(): string
    {
        return match ($this) {
            self::Configured => 'Configured',
            self::Healthy => 'Healthy',
            self::Unhealthy => 'Unhealthy',
            self::NotConfigured => 'Not configured',
        };
    }
}
