<?php

namespace App\Enums;

enum Workspace: string
{
    case Buyer = 'buyer';
    case Broker = 'broker';
    case Seller = 'seller';
    case Investor = 'investor';
    case Staff = 'staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Buyer => 'Buyer',
            self::Broker => 'Broker Partner',
            self::Seller => 'Landowner',
            self::Investor => 'Investor',
            self::Staff => 'Operations',
            self::Admin => 'Administration',
        };
    }

    public function homeRoute(): string
    {
        return $this->value.'.dashboard';
    }

    public function permission(): string
    {
        return 'workspace.'.$this->value;
    }
}
