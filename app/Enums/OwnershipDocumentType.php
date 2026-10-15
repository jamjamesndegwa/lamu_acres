<?php

namespace App\Enums;

enum OwnershipDocumentType: string
{
    case NationalId = 'national_id';
    case TitleDeed = 'title_deed';
    case OfficialSearch = 'official_search';
    case SurveyPlan = 'survey_plan';
    case MarketingAuthority = 'marketing_authority';
    case CoOwnerAuthorization = 'co_owner_authorization';
    case SpousalConsent = 'spousal_consent';
    case PowerOfAttorney = 'power_of_attorney';
    case CompanyResolution = 'company_resolution';
    case SuccessionAuthority = 'succession_authority';
    case HeadLease = 'head_lease';
    case LandRates = 'land_rates';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => 'National ID or passport',
            self::TitleDeed => 'Title deed or certificate of lease',
            self::OfficialSearch => 'Official search certificate',
            self::SurveyPlan => 'Survey, RIM or deed plan',
            self::MarketingAuthority => 'Signed marketing authority',
            self::CoOwnerAuthorization => 'Co-owner authorization',
            self::SpousalConsent => 'Spousal consent',
            self::PowerOfAttorney => 'Power of attorney',
            self::CompanyResolution => 'Company resolution and corporate authority',
            self::SuccessionAuthority => 'Estate or succession authorization',
            self::HeadLease => 'Existing lease or head lease',
            self::LandRates => 'Land rates or land rent evidence',
            self::Other => 'Other supporting legal document',
        };
    }

    /**
     * The Verification Passport checklist item this evidence supports.
     */
    public function category(): ?VerificationCategory
    {
        return match ($this) {
            self::TitleDeed, self::HeadLease => VerificationCategory::TitleOwnership,
            self::OfficialSearch => VerificationCategory::OfficialSearch,
            self::SurveyPlan => VerificationCategory::SurveyBoundaries,
            self::LandRates => VerificationCategory::LandRates,
            self::NationalId,
            self::MarketingAuthority,
            self::CoOwnerAuthorization,
            self::SpousalConsent,
            self::PowerOfAttorney,
            self::CompanyResolution,
            self::SuccessionAuthority => VerificationCategory::SellerAuthority,
            self::Other => null,
        };
    }

    public function sensitivity(): EvidenceSensitivity
    {
        return match ($this) {
            self::NationalId => EvidenceSensitivity::OwnerId,
            self::TitleDeed, self::HeadLease, self::OfficialSearch => EvidenceSensitivity::Title,
            self::SurveyPlan => EvidenceSensitivity::Parcel,
            default => EvidenceSensitivity::General,
        };
    }
}
