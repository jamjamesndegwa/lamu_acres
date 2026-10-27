<?php

namespace App\Enums;

enum MessageEvent: string
{
    case AccountRegistered = 'account_registered';
    case BrokerApproved = 'broker_approved';
    case ApplicationDecision = 'application_decision';
    case PropertyApproved = 'property_approved';
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

    public function label(): string
    {
        return match ($this) {
            self::AccountRegistered => 'Account registration',
            self::BrokerApproved => 'Broker approval',
            self::ApplicationDecision => 'Partner application update',
            self::PropertyApproved => 'Property approval',
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
