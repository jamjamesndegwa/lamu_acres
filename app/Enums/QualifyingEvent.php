<?php

namespace App\Enums;

enum QualifyingEvent: string
{
    case SaleCompleted = 'sale_completed';

    public function label(): string
    {
        return 'Successful completion';
    }
}
