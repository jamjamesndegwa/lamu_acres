<?php

namespace App\Enums;

enum VerificationCategory: string
{
    case TitleOwnership = 'title_ownership';
    case OfficialSearch = 'official_search';
    case SellerAuthority = 'seller_authority';
    case SurveyBoundaries = 'survey_boundaries';
    case LandRates = 'land_rates';
    case LandRent = 'land_rent';
    case Encumbrances = 'encumbrances';
    case AccessRights = 'access_rights';
    case Zoning = 'zoning';
    case Environmental = 'environmental';
    case Utilities = 'utilities';
    case TransactionReadiness = 'transaction_readiness';

    public function label(): string
    {
        return match ($this) {
            self::TitleOwnership => 'Title and ownership',
            self::OfficialSearch => 'Official search',
            self::SellerAuthority => 'Seller authority',
            self::SurveyBoundaries => 'Survey and parcel boundaries',
            self::LandRates => 'Land rates',
            self::LandRent => 'Land rent',
            self::Encumbrances => 'Encumbrances and restrictions',
            self::AccessRights => 'Access rights',
            self::Zoning => 'Zoning and land use',
            self::Environmental => 'Environmental restrictions',
            self::Utilities => 'Utilities and infrastructure',
            self::TransactionReadiness => 'Transaction readiness',
        };
    }

    public function critical(): bool
    {
        return match ($this) {
            self::TitleOwnership, self::OfficialSearch, self::SellerAuthority, self::Encumbrances => true,
            default => false,
        };
    }
}
