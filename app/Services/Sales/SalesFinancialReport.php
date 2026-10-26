<?php

namespace App\Services\Sales;

use App\Enums\CommissionStatus;
use App\Enums\SalesStage;
use App\Models\AgencyFeeAgreement;
use App\Models\CommissionAgreement;
use App\Models\SalesTransaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class SalesFinancialReport
{
    public function __construct(
        private ?CarbonInterface $from = null,
        private ?CarbonInterface $to = null,
    ) {}

    public static function between(?CarbonInterface $from, ?CarbonInterface $to): self
    {
        return new self($from, $to);
    }

    /**
     * @return array<string, int|float>
     */
    public function summary(): array
    {
        $completed = $this->completedTransactions();

        return [
            'completed_transaction_count' => (int) $completed->count(),
            'completed_sales_value' => (int) $completed->sum('agreed_price'),
            'total_acreage_sold' => (float) $completed->sum('acreage'),
            'gross_agency_fees_earned' => (int) $this->agencyFeesQuery()->sum('earned_amount'),
            'agency_fees_collected' => (int) $this->agencyFeesQuery()->sum('collected_amount'),
            'outstanding_agency_receivables' => (int) $this->agencyFeesQuery()
                ->get()
                ->sum(fn (AgencyFeeAgreement $row): int => $row->outstandingReceivable()),
            'broker_commissions_earned' => (int) $this->commissionQuery()->sum('earned_amount'),
            'broker_commissions_paid' => (int) $this->commissionQuery()->sum('paid_amount'),
            'outstanding_broker_liabilities' => (int) $this->commissionQuery()
                ->get()
                ->sum(fn (CommissionAgreement $row): int => $row->outstandingPayable()),
        ];
    }

    /**
     * @return Builder<SalesTransaction>
     */
    private function completedTransactions(): Builder
    {
        return SalesTransaction::query()
            ->where('stage', SalesStage::Completed)
            ->when($this->from, fn ($query) => $query->where('completed_at', '>=', $this->from))
            ->when($this->to, fn ($query) => $query->where('completed_at', '<=', $this->to));
    }

    /**
     * @return Builder<AgencyFeeAgreement>
     */
    private function agencyFeesQuery(): Builder
    {
        return AgencyFeeAgreement::query()
            ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid])
            ->when($this->from, fn ($query) => $query->whereHas('transaction', fn ($tx) => $tx->where('completed_at', '>=', $this->from)))
            ->when($this->to, fn ($query) => $query->whereHas('transaction', fn ($tx) => $tx->where('completed_at', '<=', $this->to)));
    }

    /**
     * @return Builder<CommissionAgreement>
     */
    private function commissionQuery(): Builder
    {
        return CommissionAgreement::query()
            ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid])
            ->when($this->from, fn ($query) => $query->whereHas('transaction', fn ($tx) => $tx->where('completed_at', '>=', $this->from)))
            ->when($this->to, fn ($query) => $query->whereHas('transaction', fn ($tx) => $tx->where('completed_at', '<=', $this->to)));
    }
}
