<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CommissionStatus;
use App\Enums\SalesStage;
use App\Http\Controllers\Controller;
use App\Models\AgencyFeeAgreement;
use App\Models\AgencyFeeReceipt;
use App\Models\CommissionAgreement;
use App\Models\CommissionPayment;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Admin\AdminInventoryMetrics;
use App\Services\Sales\SalesFinancialReport;
use App\Support\CsvExport;
use App\Support\Reporting\ReportingFilters;
use App\Support\Reporting\ReportingWindow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportExportController extends Controller
{
    public function __invoke(Request $request, string $dataset): StreamedResponse
    {
        $window = ReportingWindow::fromRequest($request);
        $filters = ReportingFilters::fromRequest($request);
        $from = $window->isAllRecords() ? null : $window->from;
        $to = $window->isAllRecords() ? null : $window->to;
        $financial = SalesFinancialReport::between($from, $to, $filters);

        return match ($dataset) {
            'completed-sales' => $this->completedSales($financial, $window),
            'inventory' => $this->inventory($filters),
            'agency-earnings' => $this->agencyEarnings($financial, $window, $filters),
            'agency-collections' => $this->agencyCollections($from, $to, $filters),
            'receivables' => $this->receivables($filters),
            'broker-commissions' => $this->brokerCommissions($financial, $filters),
            'broker-payouts' => $this->brokerPayouts($from, $to, $filters),
            'broker-performance' => $this->brokerPerformance($financial, $filters),
            default => abort(404),
        };
    }

    private function completedSales(SalesFinancialReport $financial, ReportingWindow $window): StreamedResponse
    {
        $query = $financial->completedTransactions();

        return CsvExport::download('completed-sales.csv', [
            'Reference', 'Property', 'Buyer', 'Officer', 'Acreage', 'Agreed price', 'Completed at', 'Stage',
        ], $this->chunkSales($query));
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    private function chunkSales(Builder $query): \Generator
    {
        foreach ($query->with(['property:id,title', 'buyer:id,name', 'agent:id,name'])->orderBy('id')->cursor() as $sale) {
            /** @var SalesTransaction $sale */
            yield [
                $sale->reference,
                $sale->property?->title ?? '',
                $sale->buyer?->name ?? '',
                $sale->agent?->name ?? '',
                $sale->acreage === null ? '' : number_format((float) $sale->acreage, 4, '.', ''),
                $sale->agreed_price === null ? '' : (string) $sale->agreed_price,
                $sale->completed_at?->toDateString() ?? '',
                $sale->stage->value,
            ];
        }
    }

    private function inventory(ReportingFilters $filters): StreamedResponse
    {
        $metrics = app(AdminInventoryMetrics::class)->summarize($filters);

        return CsvExport::download('property-inventory.csv', ['Metric', 'Value'], [
            ['Published listings', (string) $metrics['published_listings']],
            ['Registered acreage', (string) $metrics['registered_acreage']],
            ['Offered acreage', (string) $metrics['offered_acreage']],
            ['Sold acreage', (string) $metrics['sold_acreage']],
            ['Remaining acreage', (string) $metrics['remaining_acreage']],
            ['Fully sold parcels', (string) $metrics['fully_sold_parcels']],
            ['Partially sold parcels', (string) $metrics['partially_sold_parcels']],
        ]);
    }

    private function agencyEarnings(SalesFinancialReport $financial, ReportingWindow $window, ReportingFilters $filters): StreamedResponse
    {
        $query = AgencyFeeAgreement::query()
            ->with(['transaction:id,reference', 'property:id,title', 'seller:id,name'])
            ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid])
            ->whereHas('transaction', function (Builder $transaction) use ($window, $filters): void {
                $transaction->where('stage', SalesStage::Completed);
                $filters->applyToSales($transaction);

                if (! $window->isAllRecords()) {
                    $transaction->whereBetween('completed_at', [$window->from, $window->to]);
                }
            });

        return CsvExport::download('agency-earnings.csv', [
            'Transaction', 'Property', 'Agreement', 'Earned', 'Collected', 'Outstanding',
        ], $this->chunkAgencyAgreements($query));
    }

    private function agencyCollections(?CarbonImmutable $from, ?CarbonImmutable $to, ReportingFilters $filters): StreamedResponse
    {
        $query = AgencyFeeReceipt::query()
            ->with(['agreement.transaction:id,reference', 'agreement.property:id,title'])
            ->where('status', 'collected')
            ->when($from, fn ($builder) => $builder->where('collected_at', '>=', $from))
            ->when($to, fn ($builder) => $builder->where('collected_at', '<=', $to))
            ->whereHas('agreement.transaction', fn (Builder $transaction) => $filters->applyToSales($transaction));

        return CsvExport::download('agency-collections.csv', [
            'Receipt', 'Transaction', 'Property', 'Amount', 'Collected at',
        ], $this->chunkAgencyReceipts($query));
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    private function chunkAgencyReceipts(Builder $query): \Generator
    {
        foreach ($query->orderBy('id')->cursor() as $receipt) {
            yield [
                $receipt->reference,
                $receipt->agreement?->transaction?->reference ?? '',
                $receipt->agreement?->property?->title ?? '',
                (string) $receipt->amount,
                $receipt->collected_at?->toDateString() ?? '',
            ];
        }
    }

    private function receivables(ReportingFilters $filters): StreamedResponse
    {
        $query = AgencyFeeAgreement::query()
            ->with(['transaction:id,reference', 'property:id,title'])
            ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable])
            ->whereColumn('collected_amount', '<', 'earned_amount')
            ->whereHas('transaction', fn (Builder $transaction) => $filters->applyToSales($transaction));

        return CsvExport::download('outstanding-receivables.csv', [
            'Transaction', 'Property', 'Agreement', 'Earned', 'Collected', 'Outstanding',
        ], $this->chunkAgencyAgreements($query));
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    private function chunkAgencyAgreements(Builder $query): \Generator
    {
        foreach ($query->orderBy('id')->cursor() as $agreement) {
            /** @var AgencyFeeAgreement $agreement */
            yield [
                $agreement->transaction?->reference ?? '',
                $agreement->property?->title ?? '',
                $agreement->reference,
                (string) ($agreement->earned_amount ?? 0),
                (string) $agreement->collected_amount,
                (string) $agreement->outstandingReceivable(),
            ];
        }
    }

    private function brokerCommissions(SalesFinancialReport $financial, ReportingFilters $filters): StreamedResponse
    {
        $query = CommissionAgreement::query()
            ->with(['broker:id,name', 'transaction:id,reference'])
            ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid])
            ->whereHas('transaction', function (Builder $transaction) use ($filters): void {
                $transaction->where('stage', SalesStage::Completed);
                $filters->applyToSales($transaction);
            });

        return CsvExport::download('broker-commissions.csv', [
            'Agreement', 'Broker', 'Transaction', 'Earned', 'Paid', 'Outstanding', 'Status',
        ], $this->chunkCommissionAgreements($query));
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    private function chunkCommissionAgreements(Builder $query): \Generator
    {
        foreach ($query->orderBy('id')->cursor() as $agreement) {
            yield [
                $agreement->reference,
                $agreement->broker?->name ?? '',
                $agreement->transaction?->reference ?? '',
                (string) ($agreement->earned_amount ?? 0),
                (string) $agreement->paid_amount,
                (string) $agreement->outstandingPayable(),
                $agreement->status->value,
            ];
        }
    }

    private function brokerPayouts(?CarbonImmutable $from, ?CarbonImmutable $to, ReportingFilters $filters): StreamedResponse
    {
        $query = CommissionPayment::query()
            ->with(['agreement.broker:id,name', 'agreement.transaction:id,reference'])
            ->where('status', 'paid')
            ->when($from, fn ($builder) => $builder->where('paid_at', '>=', $from))
            ->when($to, fn ($builder) => $builder->where('paid_at', '<=', $to))
            ->whereHas('agreement.transaction', fn (Builder $transaction) => $filters->applyToSales($transaction));

        return CsvExport::download('broker-payouts.csv', [
            'Reference', 'Broker', 'Transaction', 'Amount', 'Paid at',
        ], $this->chunkCommissionPayments($query));
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    private function chunkCommissionPayments(Builder $query): \Generator
    {
        foreach ($query->orderBy('id')->cursor() as $payment) {
            yield [
                $payment->reference,
                $payment->agreement?->broker?->name ?? '',
                $payment->agreement?->transaction?->reference ?? '',
                (string) $payment->amount,
                $payment->paid_at?->toDateString() ?? '',
            ];
        }
    }

    private function brokerPerformance(SalesFinancialReport $financial, ReportingFilters $filters): StreamedResponse
    {
        $brokers = User::role('broker')->whereNull('suspended_at')->orderBy('name')->cursor();

        return CsvExport::download('broker-performance.csv', [
            'Broker', 'Completed deals', 'Acreage sold', 'Sale value', 'Commission earned', 'Paid', 'Outstanding',
        ], $this->chunkBrokerPerformance($financial, $brokers));
    }

    /**
     * @param  LazyCollection<int, User>|\Traversable<int, User>  $brokers
     * @return \Generator<int, array<int, string>>
     */
    private function chunkBrokerPerformance(SalesFinancialReport $financial, iterable $brokers): \Generator
    {
        foreach ($brokers as $broker) {
            $sales = $financial->completedTransactions()->where(function (Builder $builder) use ($broker): void {
                $builder->whereHas('introduction', fn ($intro) => $intro->where('broker_id', $broker->id))
                    ->orWhereHas('property', fn ($property) => $property->where('submitted_by', $broker->id));
            });

            $dealCount = (clone $sales)->count();
            $acreage = (float) (clone $sales)->sum('acreage');
            $value = (int) (clone $sales)->sum('agreed_price');

            $agreements = CommissionAgreement::query()
                ->where('broker_id', $broker->id)
                ->whereIn('status', [CommissionStatus::Earned, CommissionStatus::Payable, CommissionStatus::Paid])
                ->get();

            $earned = (int) $agreements->sum('earned_amount');
            $paid = (int) $agreements->sum('paid_amount');
            $outstanding = (int) $agreements->sum(fn (CommissionAgreement $row): int => $row->outstandingPayable());

            yield [
                $broker->name,
                (string) $dealCount,
                number_format($acreage, 4, '.', ''),
                (string) $value,
                (string) $earned,
                (string) $paid,
                (string) $outstanding,
            ];
        }
    }
}
