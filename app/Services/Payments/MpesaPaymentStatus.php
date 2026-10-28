<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use App\Models\SubscriptionPayment;

class MpesaPaymentStatus
{
    public static function headline(SubscriptionPayment $payment): string
    {
        return match ($payment->status) {
            PaymentStatus::Pending => self::pendingHeadline($payment),
            PaymentStatus::Succeeded => 'Payment confirmed',
            PaymentStatus::Failed => self::failedHeadline($payment),
            PaymentStatus::TimedOut => 'Payment timed out',
        };
    }

    public static function detail(SubscriptionPayment $payment): string
    {
        return match ($payment->status) {
            PaymentStatus::Pending => 'Approve the STK prompt on your phone. Investor Pass stays closed until Lamu Acres reconciles a verified M-Pesa receipt—sending the prompt is not payment success.',
            PaymentStatus::Succeeded => 'Your M-Pesa payment was verified. Investor Pass access follows your membership dates below.',
            PaymentStatus::Failed => $payment->provider_result_desc ?: 'The provider reported that this payment did not complete. You can retry if eligible.',
            PaymentStatus::TimedOut => 'The handset did not confirm in time. You can retry with a new prompt if eligible.',
        };
    }

    public static function canRetry(SubscriptionPayment $payment): bool
    {
        return in_array($payment->status, [PaymentStatus::Failed, PaymentStatus::TimedOut], true);
    }

    private static function pendingHeadline(SubscriptionPayment $payment): string
    {
        if ($payment->checkout_request_id === null) {
            return 'Unable to reach Safaricom';
        }

        return 'Awaiting verification';
    }

    private static function failedHeadline(SubscriptionPayment $payment): string
    {
        $code = $payment->provider_result_code;

        return match ($code) {
            '1032' => 'Payment cancelled on phone',
            '1' => 'Payment declined',
            default => 'Payment failed',
        };
    }
}
