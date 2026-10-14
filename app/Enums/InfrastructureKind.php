<?php

namespace App\Enums;

enum InfrastructureKind: string
{
    case Road = 'road';
    case Water = 'water';
    case Electricity = 'electricity';
    case Jetty = 'jetty';
    case Airstrip = 'airstrip';
    case Health = 'health';
    case School = 'school';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Road => 'Road',
            self::Water => 'Water',
            self::Electricity => 'Electricity',
            self::Jetty => 'Jetty',
            self::Airstrip => 'Airstrip',
            self::Health => 'Health facility',
            self::School => 'School',
            self::Other => 'Other',
        };
    }
}
