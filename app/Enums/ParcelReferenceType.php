<?php

namespace App\Enums;

enum ParcelReferenceType: string
{
    case TitleNumber = 'title_number';
    case LandReference = 'land_reference';
    case AllotmentLetter = 'allotment_letter';
    case AdjudicationNumber = 'adjudication_number';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TitleNumber => 'Title number',
            self::LandReference => 'L.R. number',
            self::AllotmentLetter => 'Allotment letter reference',
            self::AdjudicationNumber => 'Adjudication or plot number',
            self::Other => 'Other land reference',
        };
    }
}
