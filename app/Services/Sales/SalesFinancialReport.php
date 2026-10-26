<?php

namespace App\Services\Sales;

use App\Enums\CommissionStatus;
use App\Enums\SalesStage;
use App\Models\AgencyFeeAgreement;
use App\Models\AgencyFeeReceipt;
use App\Models\CommissionAgreement;
use App\Models\CommissionPayment;
use App\Models\Enquiry;
use App\Models\OfferPresentation;
use App\Models\SalesTransaction;
use App\Support\Reporting\ReportingFilters;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class SalesFinancialReport
{
    public function __construct(
        private ?CarbonInterface $from = null,
        private ?CarbonInterface $to = null,
        private ReportingFilters $filters = new ReportingFilters,
    ) {}

    public static function between(?CarbonInterface $from, ?CarbonInterface $to, ?ReportingFilters $filters = null): self
    {
        return new self($from, $to, $filters ?? ReportingFilters::empty());
    }

    /**
     * @return array<string, int|float|string|null>
     */
    public function summary(): array
    {
        $completed = $this->completedTransactions();
        $count = (int) (clone $completed)->count();
        $value = (int) (clone $completed)->sum('agreed_price');
        $earned = (int) $this->agencyFeesEarnedQuery()->sum('earned_amount');
        $collected = $this->agencyCollectedInPeriod();

        return [
            'completed_transaction_count' => $count,
            'completed_sales_value' => $value,
            'total_acreage_sold' => (float) (clone $completed)->sum('acreage'),
            'average_completed_sale_value' => $count > 0 ? (int) round($value / $count) : 0,
            'gross_agency_fees_earned' => $earned,
            'agency_fees_collected' => $collected,
            'outstanding_agency_receivables' => (int) $this->agencyFeesEarnedQuery()
                ->get()
                ->sum(fn (AgencyFeeAgreement $row): int => $row->outstandingReceivable()),
            'agency_collection_rate' => $earned > 0 ? round(($collected / $earned) * 100, 2) : null,
            'broker_commissions_earned' => (int) $this->commissionEarnedQuery()->sum('earned_amount'),
            'broker_commissions_payable' => (int) CommissionAgreement::query()
                ->where('status', CommissionStatus::Payable)
                ->sum('earned_amount'),
            'broker_commissions_paid' => (int) $this->commissionPaidInPeriod(),
            'outstanding_broker_liabilities' => (int) $this->commissionEarnedQuery()
                ->get()
                ->sum(fn (CommissionAgreement $row): int => $row->outstandingPayable()),
            'active_pipeline_count' => (int) $this->openSales()->count(),
            'pending_closings' => (int) SalesTransaction::query()
                ->where('stage', SalesStage::CompletionPending)
                ->whereNull('outcome')
                ->count(),
            'completed_sales_this_month' => (int) $this->completedTransactions(
                CarbonImmutable::now()->startOfMonth(),
                CarbonImmutable::now()->endOfDay(),
            )->count(),
            'agency_collections_this_month' => $this->agencyCollectedInPeriod(
                CarbonImmutable::now()->startOfMonth(),
                CarbonImmutable::now()->endOfDay(),
            ),
            'open_purchase_requests' => (int) Enquiry::query()->where('purchase_request', true)->whereNull('closed_at')->count(),
            'deals_awaiting_seller_response' => (int) OfferPresentation::query()->whereNull('seller_response')->count(),
            'agency_fee_exceptions' => (int) $this->completedTransactions()
                ->whereDoesntHave('agencyFeeAgreements', fn ($q) => $q->whereIn('status', [
                    CommissionStatus::Earned,
                    CommissionStatus::Payable,
                    CommissionStatus::Paid,
                ]))
                ->count(),
            'outstanding_collection_followups' => (int) AgencyFeeAgreement::query()
                ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable])
                ->whereColumn('collected_amount', '<', 'earned_amount')
                ->count(),
            'commission_settlement_exceptions' => (int) CommissionAgreement::query()
                ->where(function (Builder $query): void {
                    $query->where('status', CommissionStatus::Disputed)
                        ->orWhereHas('payments', fn ($payment) => $payment->where('status', 'initiated'));
                })
                ->count(),
        ];
    }

    public function agencyCollectedInPeriod(?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        $from ??= $this->from;
        $to ??= $this->to;

        return (int) AgencyFeeReceipt::query()
            ->where('status', 'collected')
            ->when($from, fn ($query) => $query->where('collected_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('collected_at', '<=', $to))
            ->sum('amount');
    }

    public function commissionPaidInPeriod(?CarbonInterface $from = null, ?CarbonInterface $to = null): int
    {
        $from ??= $this->from;
        $to ??= $this->to;

        return (int) CommissionPayment::query()
            ->where('status', 'paid')
            ->when($from, fn ($query) => $query->where('paid_at', '>=', $from))
            ->when($to, fn ($query) => $query->where('paid_at', '<=', $to))
            ->sum('amount');
    }

    /**
     * @return array<int, array{month: string, sales_value: int, agency_earned: int, agency_collected: int, acreage: float, commission_earned: int, commission_paid: int}>
     */
    public function monthlyTrends(int $months = 6): array
    {
        $start = CarbonImmutable::now()->subMonths($months - 1)->startOfMonth();
        $buckets = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->addMonths($i);
            $key = $month->format('Y-m');
            $buckets[$key] = [
                'month' => $month->format('M Y'),
                'sales_value' => 0,
                'agency_earned' => 0,
                'agency_collected' => 0,
                'acreage' => 0.0,
                'commission_earned' => 0,
                'commission_paid' => 0,
            ];
        }

        $this->completedTransactions($start, CarbonImmutable::now()->endOfDay())
            ->get(['completed_at', 'agreed_price', 'acreage'])
            ->each(function (SalesTransaction $sale) use (&$buckets): void {
                if ($sale->completed_at === null) {
                    return;
                }

                $key = $sale->completed_at->format('Y-m');

                if (! isset($buckets[$key])) {
                    return;
                }

                $buckets[$key]['sales_value'] += (int) ($sale->agreed_price ?? 0);
                $buckets[$key]['acreage'] += (float) ($sale->acreage ?? 0);
            });

        AgencyFeeAgreement::query()
            ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid])
            ->with('transaction:id,completed_at')
            ->whereHas('transaction', fn ($tx) => $tx->where('completed_at', '>=', $start))
            ->get(['earned_amount', 'sales_transaction_id'])
            ->each(function (AgencyFeeAgreement $agreement) use (&$buckets): void {
                $completedAt = $agreement->transaction?->completed_at;

                if ($completedAt === null) {
                    return;
                }

                $key = $completedAt->format('Y-m');

                if (! isset($buckets[$key])) {
                    return;
                }

                $buckets[$key]['agency_earned'] += (int) ($agreement->earned_amount ?? 0);
            });

        AgencyFeeReceipt::query()
            ->where('status', 'collected')
            ->where('collected_at', '>=', $start)
            ->get(['amount', 'collected_at'])
            ->each(function (AgencyFeeReceipt $receipt) use (&$buckets): void {
                if ($receipt->collected_at === null) {
                    return;
                }

                $key = $receipt->collected_at->format('Y-m');

                if (! isset($buckets[$key])) {
                    return;
                }

                $buckets[$key]['agency_collected'] += (int) $receipt->amount;
            });

        CommissionAgreement::query()
            ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid])
            ->with('transaction:id,completed_at')
            ->whereHas('transaction', fn ($tx) => $tx->where('completed_at', '>=', $start))
            ->get(['earned_amount', 'sales_transaction_id'])
            ->each(function (CommissionAgreement $agreement) use (&$buckets): void {
                $completedAt = $agreement->transaction?->completed_at;

                if ($completedAt === null) {
                    return;
                }

                $key = $completedAt->format('Y-m');

                if (! isset($buckets[$key])) {
                    return;
                }

                $buckets[$key]['commission_earned'] += (int) ($agreement->earned_amount ?? 0);
            });

        CommissionPayment::query()
            ->where('status', 'paid')
            ->where('paid_at', '>=', $start)
            ->get(['amount', 'paid_at'])
            ->each(function (CommissionPayment $payment) use (&$buckets): void {
                if ($payment->paid_at === null) {
                    return;
                }

                $key = $payment->paid_at->format('Y-m');

                if (! isset($buckets[$key])) {
                    return;
                }

                $buckets[$key]['commission_paid'] += (int) $payment->amount;
            });

        return array_values($buckets);
    }

    /**
     * @return Builder<SalesTransaction>
     */
    public function completedTransactions(?CarbonInterface $from = null, ?CarbonInterface $to = null): Builder
    {
        $from ??= $this->from;
        $to ??= $this->to;

        $query = SalesTransaction::query()->where('stage', SalesStage::Completed);

        $this->filters->applyToSales($query);

        if ($from !== null) {
            $query->where('completed_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('completed_at', '<=', $to);
        }

        return $query;
    }

    /**
     * @return Builder<SalesTransaction>
     */
    public function openSales(): Builder
    {
        $query = SalesTransaction::query()
            ->whereNull('outcome')
            ->where('stage', '!=', SalesStage::Completed);

        return $this->filters->applyToSales($query);
    }

    /**
     * @return Builder<AgencyFeeAgreement>
     */
    private function agencyFeesEarnedQuery(): Builder
    {
        $query = AgencyFeeAgreement::query()
            ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid]);

        if ($this->from !== null || $this->to !== null) {
            $query->whereHas('transaction', function (Builder $transaction): void {
                if ($this->from !== null) {
                    $transaction->where('completed_at', '>=', $this->from);
                }

                if ($this->to !== null) {
                    $transaction->where('completed_at', '<=', $this->to);
                }
            });
        }

        return $query;
    }

    /**
     * @return Builder<CommissionAgreement>
     */
    private function commissionEarnedQuery(): Builder
    {
        $query = CommissionAgreement::query()
            ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid]);

        if ($this->from !== null || $this->to !== null) {
            $query->whereHas('transaction', function (Builder $transaction): void {
                if ($this->from !== null) {
                    $transaction->where('completed_at', '>=', $this->from);
                }

                if ($this->to !== null) {
                    $transaction->where('completed_at', '<=', $this->to);
                }
            });
        }

        return $query;
    }
}
