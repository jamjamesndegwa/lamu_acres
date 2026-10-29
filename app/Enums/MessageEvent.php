<?php

namespace App\Enums;

enum MessageEvent: string
{
    case AccountRegistered = 'account_registered';
    case PasswordChanged = 'password_changed';
    case PasswordResetRequested = 'password_reset_requested';
    case EmailVerificationRequested = 'email_verification_requested';
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
    case BuyerEnquiryAcknowledged = 'buyer_enquiry_acknowledged';
    case PurchaseRequestAcknowledged = 'purchase_request_acknowledged';
    case PurchaseRequestStaffAlert = 'purchase_request_staff_alert';
    case SellerPropertyInterest = 'seller_property_interest';
    case BrokerListingInterest = 'broker_listing_interest';
    case SourcingRequestAcknowledged = 'sourcing_request_acknowledged';
    case SourcingStaffAlert = 'sourcing_staff_alert';
    case EnquiryResponseReceived = 'enquiry_response_received';
    case EnquiryClosedBuyer = 'enquiry_closed_buyer';
    case DocumentAccessAcknowledged = 'document_access_acknowledged';
    case DocumentAccessStaffAlert = 'document_access_staff_alert';
    case SiteVisitRequestedAcknowledged = 'site_visit_requested_acknowledged';
    case SiteVisitStaffAlert = 'site_visit_staff_alert';
    case SiteVisitRescheduled = 'site_visit_rescheduled';
    case SiteVisitCancelled = 'site_visit_cancelled';
    case SiteVisitReminder = 'site_visit_reminder';
    case SiteVisitFollowUpRequired = 'site_visit_follow_up_required';
    case SourcingMatch = 'sourcing_match';
    case SiteVisitConfirmed = 'site_visit_confirmed';
    case ReferralAccepted = 'referral_accepted';
    case OfferSubmittedAcknowledged = 'offer_submitted_acknowledged';
    case OfferSubmittedStaffAlert = 'offer_submitted_staff_alert';
    case OfferSubmittedBrokerNotice = 'offer_submitted_broker_notice';
    case OfferPresentedToSeller = 'offer_presented_to_seller';
    case OfferWithdrawnAlert = 'offer_withdrawn_alert';
    case SaleTermsAgreed = 'sale_terms_agreed';
    case SaleOutcomeUpdated = 'sale_outcome_updated';
    case ClosingEvidenceUpdate = 'closing_evidence_update';
    case ClosingReviewUpdate = 'closing_review_update';
    case DocumentAccessDecided = 'document_access_decided';
    case CommissionEarned = 'commission_earned';
    case AgencyFeePayable = 'agency_fee_payable';
    case AgencyFeeReceiptConfirmed = 'agency_fee_receipt_confirmed';
    case SubscriptionActivated = 'subscription_activated';
    case SubscriptionExpiringSoon = 'subscription_expiring_soon';
    case OfferStatus = 'offer_status';
    case ClosingCompleted = 'closing_completed';
    case CommissionPayable = 'commission_payable';
    case CommissionPaid = 'commission_paid';
    case ReportPublished = 'report_published';
    case ResearchWatchAlert = 'research_watch_alert';
    case SubscriptionRenewed = 'subscription_renewed';
    case SubscriptionExpired = 'subscription_expired';
    case MarketingBroadcast = 'marketing_broadcast';
    case StaffManualEmail = 'staff_manual_email';

    public function isMarketing(): bool
    {
        return $this === self::MarketingBroadcast;
    }

    public function isMandatory(): bool
    {
        return ! $this->isMarketing() && $this !== self::ResearchWatchAlert;
    }

    public function label(): string
    {
        return match ($this) {
            self::AccountRegistered => 'Account registration',
            self::PasswordChanged => 'Password changed',
            self::PasswordResetRequested => 'Password reset',
            self::EmailVerificationRequested => 'Email verification',
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
            self::BuyerEnquiryAcknowledged => 'Enquiry received',
            self::PurchaseRequestAcknowledged => 'Purchase request received',
            self::PurchaseRequestStaffAlert => 'Purchase request for review',
            self::SellerPropertyInterest => 'Buyer interest on your property',
            self::BrokerListingInterest => 'Buyer interest on your listing',
            self::SourcingRequestAcknowledged => 'Land search received',
            self::SourcingStaffAlert => 'Sourcing request for review',
            self::EnquiryResponseReceived => 'Enquiry response',
            self::EnquiryClosedBuyer => 'Enquiry update',
            self::DocumentAccessAcknowledged => 'Document request received',
            self::DocumentAccessStaffAlert => 'Document access request',
            self::SiteVisitRequestedAcknowledged => 'Viewing request received',
            self::SiteVisitStaffAlert => 'Viewing request for coordination',
            self::SiteVisitRescheduled => 'Viewing rescheduled',
            self::SiteVisitCancelled => 'Viewing cancelled',
            self::SiteVisitReminder => 'Upcoming viewing reminder',
            self::SiteVisitFollowUpRequired => 'Viewing follow-up',
            self::SourcingMatch => 'Buyer sourcing match',
            self::SiteVisitConfirmed => 'Site visit confirmation',
            self::ReferralAccepted => 'Referral acceptance',
            self::OfferSubmittedAcknowledged => 'Offer received',
            self::OfferSubmittedStaffAlert => 'Offer for review',
            self::OfferSubmittedBrokerNotice => 'Offer submitted for buyer',
            self::OfferPresentedToSeller => 'Offer presented',
            self::OfferWithdrawnAlert => 'Offer withdrawn',
            self::SaleTermsAgreed => 'Sale terms updated',
            self::SaleOutcomeUpdated => 'Purchase case update',
            self::ClosingEvidenceUpdate => 'Closing evidence update',
            self::ClosingReviewUpdate => 'Closing review update',
            self::DocumentAccessDecided => 'Document access decision',
            self::CommissionEarned => 'Commission earned',
            self::AgencyFeePayable => 'Agency fee payable',
            self::AgencyFeeReceiptConfirmed => 'Agency fee receipt confirmed',
            self::SubscriptionActivated => 'Investor Pass activated',
            self::SubscriptionExpiringSoon => 'Investor Pass expiring soon',
            self::OfferStatus => 'Offer status',
            self::ClosingCompleted => 'Closing completion',
            self::CommissionPayable => 'Commission payable',
            self::CommissionPaid => 'Commission paid',
            self::ReportPublished => 'Premium report published',
            self::ResearchWatchAlert => 'Research watch update',
            self::SubscriptionRenewed => 'Subscription renewal',
            self::SubscriptionExpired => 'Subscription expiration',
            self::MarketingBroadcast => 'Marketing broadcast',
            self::StaffManualEmail => 'Staff manual email',
        };
    }
}
