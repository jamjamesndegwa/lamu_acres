<?php

namespace App\Services\Sales;

use App\Enums\SaleMode;
use App\Models\SalesTransaction;

class SalesReassessment
{
    public function requiresReassessment(SalesTransaction $transaction): bool
    {
        if (! $transaction->isOpen()) {
            return false;
        }

        $property = $transaction->property;

        if ($property === null) {
            return false;
        }

        if ($transaction->acreage !== null
            && (float) $transaction->acreage > $property->remainingAcreage() + 0.0001) {
            return true;
        }

        if ($transaction->enquiry?->requested_acreage !== null
            && (float) $transaction->enquiry->requested_acreage > $property->offeredAcreage() + 0.0001) {
            return true;
        }

        $snapshot = $transaction->enquiry?->sale_mode_at_request;

        if ($snapshot !== null && $snapshot !== $property->saleMode()->value) {
            return true;
        }

        return false;
    }

    public function message(SalesTransaction $transaction): ?string
    {
        if (! $this->requiresReassessment($transaction)) {
            return null;
        }

        $property = $transaction->property;
        $parts = ['This case needs reassessment before it can progress or close.'];

        if ($transaction->acreage !== null
            && $property !== null
            && (float) $transaction->acreage > $property->remainingAcreage() + 0.0001) {
            $parts[] = 'The recorded acreage exceeds the land still held ('.number_format($property->remainingAcreage(), 2).' acres).';
        }

        if ($transaction->enquiry?->requested_acreage !== null
            && $property !== null
            && (float) $transaction->enquiry->requested_acreage > $property->offeredAcreage() + 0.0001) {
            $parts[] = 'The buyer’s requested acreage is above what is currently offered.';
        }

        $snapshot = $transaction->enquiry?->sale_mode_at_request;

        if ($snapshot !== null && $property !== null && $snapshot !== $property->saleMode()->value) {
            $parts[] = 'The listing sale mode has changed since this request was submitted.';
        }

        return implode(' ', $parts);
    }

    public function blockIfStale(SalesTransaction $transaction): void
    {
        if (! $this->requiresReassessment($transaction)) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'stage' => $this->message($transaction) ?? 'Reassess this case against the current listing terms.',
        ]);
    }
}
