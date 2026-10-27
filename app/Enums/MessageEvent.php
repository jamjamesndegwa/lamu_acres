<?php

namespace App\Enums;

enum MessageEvent: string
{
    case AccountRegistered = 'account_registered';
    case PasswordChanged = 'password_changed';
    case AccountSuspended = 'account_suspended';
    case AccountRestored = 'account_restored';
    case BrokerApproved = 'broker_approved';
    case ApplicationSubmitted = 'application_submitted';
    case ApplicationResubmitted = 'application_resubmitted';
    case ApplicationStaffAlert = 'application_staff_alert';
    case ApplicationDecision = 'application_decision';
    case ListingSubmitted = 'listing_submitted';
    case ListingStaffAlert = 'listing_staff_alert';
    case ListingInformationRequested = 'listing_information_requested';
    case ListingRejected = 'listing_rejected';
    case PropertyApproved = 'property_approved';
    case PropertyPublished = 'property_published';
    case PropertyUnpublished = 'property_unpublished';
    case ListingMaterialChange = 'listing_material_change';
    case ListingInventoryImpact = 'listing_inventory_impact';
    case BuyerEnquiry = 'buyer_enquiry';
    case SourcingMatch = 'sourcing_match';
    case SiteVisitConfirmed = 'site_visit_confirmed';
    case ReferralAccepted = 'referral_accepted';
    case OfferStatus = 'offer_status';
    case ClosingCompleted = 'closing_completed';
    case CommissionPayable = 'commission_payable';
    case CommissionPaid = 'commission_paid';
    case ReportPublished = 'report_published';
    case SubscriptionRenewed = 'subscription_renewed';
    case SubscriptionExpired = 'subscription_expired';
    case MarketingBroadcast = 'marketing_broadcast';

    public function isMarketing(): bool
    {
        return $this === self::MarketingBroadcast;
    }

    public function isMandatory(): bool
    {
        return ! $this->isMarketing();
    }

    public function label(): string
    {
        return match ($this) {
            self::AccountRegistered => 'Account registration',
            self::PasswordChanged => 'Password changed',
            self::AccountSuspended => 'Account suspended',
            self::AccountRestored => 'Account restored',
            self::BrokerApproved => 'Broker approval',
            self::ApplicationSubmitted => 'Partner application received',
            self::ApplicationResubmitted => 'Partner application resubmitted',
            self::ApplicationStaffAlert => 'Partner application for review',
            self::ApplicationDecision => 'Partner application update',
            self::ListingSubmitted => 'Listing submitted',
            self::ListingStaffAlert => 'Listing submitted for review',
            self::ListingInformationRequested => 'Listing information requested',
            self::ListingRejected => 'Listing returned for correction',
            self::PropertyApproved => 'Property approval',
            self::PropertyPublished => 'Property published',
            self::PropertyUnpublished => 'Listing unpublished',
            self::ListingMaterialChange => 'Listing updated by staff',
            self::ListingInventoryImpact => 'Listing inventory update',
            self::BuyerEnquiry => 'Buyer enquiry',
            self::SourcingMatch => 'Buyer sourcing match',
            self::SiteVisitConfirmed => 'Site visit confirmation',
            self::ReferralAccepted => 'Referral acceptance',
            self::OfferStatus => 'Offer status',
            self::ClosingCompleted => 'Closing completion',
            self::CommissionPayable => 'Commission payable',
            self::CommissionPaid => 'Commission paid',
            self::ReportPublished => 'Premium report published',
            self::SubscriptionRenewed => 'Subscription renewal',
            self::SubscriptionExpired => 'Subscription expiration',
            self::MarketingBroadcast => 'Marketing broadcast',
        };
    }
}
