<?php

namespace App\Services\Admin;

use App\Enums\BrokerIntroductionStatus;
use App\Enums\CommissionStatus;
use App\Enums\PaymentStatus;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\SalesStage;
use App\Enums\SourcingRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Models\BrokerIntroduction;
use App\Models\BrokerIntroductionAudit;
use App\Models\CommissionAgreement;
use App\Models\CommissionPayment;
use App\Models\Enquiry;
use App\Models\IntelligenceAudit;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\SalesAudit;
use App\Models\SalesTransaction;
use App\Models\SourcingRequest;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionReceipt;
use App\Models\User;
use App\Models\VerificationItem;
use App\Support\Reporting\ReportingWindow;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ExecutiveReport
{
    public function __construct(
        private readonly User $user,
        private readonly ReportingWindow $window,
    ) {}

    public static function make(User $user, ReportingWindow $window): self
    {
        return new self($user, $window);
    }

    /**
     * Bar, polygon, and line charts for the executive dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    public function gallery(): array
    {
        $charts = array_values(array_filter([
            $this->listingChart(),
            $this->pipelinePolygon(),
            $this->financePolygon(),
            $this->activityGraph(),
            $this->locationChart(),
        ]));

        return $charts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function sections(): array
    {
        return [
            $this->listings(),
            $this->sales(),
            $this->demand(),
            $this->brokers(),
            $this->finance(),
            $this->subscriptions(),
            $this->verification(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function section(string $tab): array
    {
        return match ($tab) {
            'sales' => $this->sales(),
            'inventory' => $this->listings(),
            'buyers' => $this->demand(),
            'brokers' => $this->brokers(),
            'subscriptions' => $this->subscriptions(),
            default => abort(404),
        };
    }

    /**
     * @return array<int, array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}>
     */
    public function records(string $tab): array
    {
        return match ($tab) {
            'sales' => [$this->salesTable()],
            'inventory' => [$this->inventoryTable()],
            'buyers' => [$this->sourcingTable(), $this->enquiryTable()],
            'brokers' => [$this->introductionTable(), $this->referredDealsTable()],
            'subscriptions' => [$this->subscriberTable(), $this->paymentTable(), $this->receiptTable()],
            default => abort(404),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function reportSection(string $tab): array
    {
        return match ($tab) {
            'sales' => $this->sales(),
            'commissions' => $this->commissions(),
            'financial' => $this->finance(),
            'audit' => $this->audits(),
            default => abort(404),
        };
    }

    /**
     * @return array<int, array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}>
     */
    public function reportTables(string $tab): array
    {
        return match ($tab) {
            'sales' => [$this->salesTable()],
            'commissions' => [$this->commissionAgreementTable(), $this->commissionPaymentTable()],
            'financial' => [$this->completedSaleValueTable(), $this->commissionPaymentTable(), $this->receiptTable()],
            'audit' => [$this->auditTable()],
            default => abort(404),
        };
    }

    /**
     * @return array<int, array{description: string, at: CarbonInterface, href: ?string}>
     */
    public function activity(): array
    {
        if (! $this->ready('sales_audits') && ! $this->ready('property_audits') && ! $this->ready('subscription_events') && ! $this->ready('subscription_receipts') && ! $this->ready('commission_payments')) {
            return [];
        }

        return $this->activityItems()
            ->sortByDesc(fn (array $item): int => $item['at']->getTimestamp())
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function listings(): array
    {
        if (! $this->ready('properties')) {
            return $this->unconfigured('Active property listings');
        }

        $inventory = route('admin.oversight.inventory', $this->window->query());

        return $this->configured('Active property listings', [
            $this->metric('Published and available', $this->publishedAvailable(), $inventory),
            $this->metric('Reserved', $this->statusCount(PropertyStatus::Reserved), $inventory),
            $this->metric('Sold', $this->statusCount(PropertyStatus::Sold), $inventory),
            $this->metric('Pending review', $this->statusCount(PropertyStatus::Review), $inventory),
        ], $this->listingChart());
    }

    /**
     * @return array<string, mixed>
     */
    private function sales(): array
    {
        if (! $this->ready('sales_transactions')) {
            return $this->unconfigured('Sales performance');
        }

        $href = route('admin.oversight.sales', $this->window->query());
        $completed = $this->completedSales();

        return $this->configured('Sales performance', [
            $this->metric('Active sales', $this->openSales()->count(), $href),
            $this->metric('Completed sales', (clone $completed)->count(), $href),
            $this->metric('Completed value', $this->money((int) (clone $completed)->sum('agreed_price')), $href),
        ], $this->pipelineChart());
    }

    /**
     * @return array<string, mixed>
     */
    private function demand(): array
    {
        if (! $this->ready('sourcing_requests') || ! $this->ready('enquiries')) {
            return $this->unconfigured('Buyer demand');
        }

        $href = route('admin.oversight.buyers', $this->window->query());

        return $this->configured('Buyer demand', [
            $this->metric('Open sourcing requests', $this->openSourcing()->count(), $href),
            $this->metric('Unmatched requirements', (clone $this->openSourcing())->whereDoesntHave('matches')->count(), $href),
            $this->metric('Enquiries received', $this->enquiriesInPeriod()->count(), $href),
        ], $this->locationChart() ?? $this->enquiryGraph());
    }

    /**
     * @return array<string, mixed>
     */
    private function brokers(): array
    {
        if (! $this->ready('broker_introductions') || ! $this->ready('sales_transactions')) {
            return $this->unconfigured('Broker performance');
        }

        $href = route('admin.oversight.brokers', $this->window->query());

        return $this->configured('Broker performance', [
            $this->metric('Approved brokers', User::role('broker')->whereNull('suspended_at')->count(), $href),
            $this->metric('Accepted introductions', BrokerIntroduction::query()->where('status', BrokerIntroductionStatus::Accepted)->count(), $href),
            $this->metric('Deals in progress', $this->openSales()->whereNotNull('broker_introduction_id')->count(), $href),
            $this->metric('Completed referred deals', $this->completedSales()->whereNotNull('broker_introduction_id')->count(), $href),
        ], $this->brokerPolygon());
    }

    /**
     * @return array<string, mixed>
     */
    private function finance(): array
    {
        if (! $this->ready('commission_agreements') || ! $this->ready('commission_payments') || ! $this->ready('subscription_receipts')) {
            return $this->unconfigured('Financial summary');
        }

        return $this->configured('Financial summary', [
            $this->metric('Commission earned', $this->money($this->commissionAmount(CommissionStatus::Earned)), route('admin.oversight.sales', $this->window->query())),
            $this->metric('Commission payable', $this->money($this->commissionAmount(CommissionStatus::Payable)), route('admin.oversight.sales', $this->window->query())),
            $this->metric('Commission paid', $this->money($this->commissionPaid()), route('admin.oversight.sales', $this->window->query())),
            $this->metric('Premium revenue received', $this->money($this->premiumReceived()), route('admin.oversight.subscriptions', $this->window->query())),
        ], $this->financePolygon());
    }

    /**
     * @return array<string, mixed>
     */
    private function subscriptions(): array
    {
        if (! $this->ready('subscriptions') || ! $this->ready('subscription_payments') || ! $this->ready('subscription_events')) {
            return $this->unconfigured('Subscription performance');
        }

        $href = route('admin.oversight.subscriptions', $this->window->query());

        return $this->configured('Subscription performance', [
            $this->metric('Active subscribers', $this->activeSubscriptions()->count(), $href),
            $this->metric('Pending payment attempts', SubscriptionPayment::query()->where('status', PaymentStatus::Pending)->count(), $href),
            $this->metric('Expiring within 30 days', $this->activeSubscriptions()->whereBetween('ends_at', [now(), now()->addDays(30)])->count(), $href),
            $this->metric('Subscription renewals', SubscriptionEvent::query()->where('action', 'renewed')->whereBetween('created_at', [$this->window->from, $this->window->to])->count(), $href),
        ], $this->subscriptionChart());
    }

    /**
     * @return array<string, mixed>
     */
    private function verification(): array
    {
        if (! $this->ready('verification_items')) {
            return $this->unconfigured('Property verification');
        }

        $href = $this->user->hasPermission('staff.verification') ? route('staff.verification.index') : null;
        $critical = collect(VerificationCategory::cases())
            ->filter(fn (VerificationCategory $category): bool => $category->critical())
            ->map(fn (VerificationCategory $category): string => $category->value)
            ->all();

        return $this->configured('Property verification', [
            $this->metric('Awaiting verification', $this->propertiesWithStates([
                VerificationItemState::NotSupplied,
                VerificationItemState::Received,
                VerificationItemState::UnderReview,
            ]), $href),
            $this->metric('Failed or expired critical checks', VerificationItem::query()
                ->whereIn('category', $critical)
                ->whereIn('state', [VerificationItemState::Failed, VerificationItemState::Expired])
                ->count(), $href),
            $this->metric('Verification workload', VerificationItem::query()
                ->whereIn('state', [VerificationItemState::Received, VerificationItemState::UnderReview])
                ->count(), $href),
        ]);
    }

    /**
     * @param  array<int, array{label: string, value: int|string, href: ?string}>  $metrics
     * @param  array{heading: string, rows: array<int, array{label: string, count: int}>}|null  $chart
     * @return array<string, mixed>
     */
    private function configured(string $heading, array $metrics, ?array $chart = null): array
    {
        return [
            'heading' => $heading,
            'configured' => true,
            'metrics' => $metrics,
            'chart' => $chart,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unconfigured(string $heading): array
    {
        return [
            'heading' => $heading,
            'configured' => false,
            'metrics' => [],
            'chart' => null,
        ];
    }

    /**
     * @return array{label: string, value: int|string, href: ?string}
     */
    private function metric(string $label, int|string $value, ?string $href): array
    {
        return ['label' => $label, 'value' => $value, 'href' => $href];
    }

    private function publishedAvailable(): int
    {
        return Property::query()
            ->where('status', PropertyStatus::Published)
            ->where('availability', PropertyAvailability::Available)
            ->count();
    }

    private function statusCount(PropertyStatus $status): int
    {
        return Property::query()->where('status', $status)->count();
    }

    private function openSales(): Builder
    {
        return SalesTransaction::query()
            ->whereNull('outcome')
            ->where('stage', '!=', SalesStage::Completed);
    }

    private function completedSales(): Builder
    {
        return SalesTransaction::query()
            ->where('stage', SalesStage::Completed)
            ->whereBetween('completed_at', [$this->window->from, $this->window->to]);
    }

    private function openSourcing(): Builder
    {
        return SourcingRequest::query()->where('status', '!=', SourcingRequestStatus::Closed);
    }

    private function enquiriesInPeriod(): Builder
    {
        return Enquiry::query()->whereBetween('created_at', [$this->window->from, $this->window->to]);
    }

    /**
     * @return array{type: string, heading: string, rows: array<int, array{label: string, count: int}>}|null
     */
    private function listingChart(): ?array
    {
        if (! $this->ready('properties')) {
            return null;
        }

        return [
            'type' => 'bar',
            'heading' => 'Listings by status',
            'rows' => [
                ['label' => 'Published and available', 'count' => $this->publishedAvailable()],
                ['label' => 'Reserved', 'count' => $this->statusCount(PropertyStatus::Reserved)],
                ['label' => 'Sold', 'count' => $this->statusCount(PropertyStatus::Sold)],
                ['label' => 'Pending review', 'count' => $this->statusCount(PropertyStatus::Review)],
            ],
        ];
    }

    /**
     * @return array{type: string, heading: string, rows: array<int, array{label: string, count: int}>}|null
     */
    private function pipelineChart(): ?array
    {
        if (! $this->ready('sales_transactions')) {
            return null;
        }

        $rows = SalesTransaction::query()
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $chart = [];

        foreach (SalesStage::cases() as $stage) {
            $chart[] = ['label' => $stage->label(), 'count' => (int) ($rows[$stage->value] ?? 0)];
        }

        return ['type' => 'bar', 'heading' => 'Pipeline by status', 'rows' => $chart];
    }

    /**
     * @return array{type: string, heading: string, rows: array<int, array{label: string, count: int}>}|null
     */
    private function pipelinePolygon(): ?array
    {
        $chart = $this->pipelineChart();

        if ($chart === null) {
            return null;
        }

        $chart['type'] = 'polygon';
        $chart['heading'] = 'Sales pipeline';

        return $chart;
    }

    /**
     * @return array{type: string, heading: string, rows: array<int, array{label: string, count: int}>}|null
     */
    private function brokerPolygon(): ?array
    {
        if (! $this->ready('broker_introductions') || ! $this->ready('sales_transactions')) {
            return null;
        }

        return [
            'type' => 'polygon',
            'heading' => 'Broker position',
            'rows' => [
                ['label' => 'Approved brokers', 'count' => User::role('broker')->whereNull('suspended_at')->count()],
                ['label' => 'Accepted introductions', 'count' => BrokerIntroduction::query()->where('status', BrokerIntroductionStatus::Accepted)->count()],
                ['label' => 'Deals in progress', 'count' => $this->openSales()->whereNotNull('broker_introduction_id')->count()],
                ['label' => 'Completed referred deals', 'count' => $this->completedSales()->whereNotNull('broker_introduction_id')->count()],
            ],
        ];
    }

    /**
     * @return array{type: string, heading: string, series: array<int, array{label: string, points: array<int, array{label: string, count: int}>}>}|null
     */
    private function activityGraph(): ?array
    {
        if (! $this->ready('enquiries') || ! $this->ready('sales_transactions') || ! $this->ready('subscription_receipts')) {
            return null;
        }

        return [
            'type' => 'line',
            'heading' => 'Activity over the period',
            'series' => [
                ['label' => 'Enquiries', 'points' => $this->points($this->countsByDay($this->enquiriesInPeriod(), 'created_at'))],
                ['label' => 'Completed sales', 'points' => $this->points($this->countsByDay($this->completedSales(), 'completed_at'))],
                ['label' => 'Premium receipts', 'points' => $this->points($this->countsByDay(
                    SubscriptionReceipt::query()->where('currency', 'KES')->whereBetween('issued_at', [$this->window->from, $this->window->to]),
                    'issued_at',
                ))],
            ],
        ];
    }

    /**
     * @return array{type: string, heading: string, series: array<int, array{label: string, points: array<int, array{label: string, count: int}>}>}|null
     */
    private function enquiryGraph(): ?array
    {
        if (! $this->ready('enquiries')) {
            return null;
        }

        return [
            'type' => 'line',
            'heading' => 'Enquiries over the period',
            'series' => [
                ['label' => 'Enquiries', 'points' => $this->points($this->countsByDay($this->enquiriesInPeriod(), 'created_at'))],
            ],
        ];
    }

    /**
     * @return array{type: string, heading: string, rows: array<int, array{label: string, count: int}>}|null
     */
    private function subscriptionChart(): ?array
    {
        if (! $this->ready('subscriptions') || ! $this->ready('subscription_payments') || ! $this->ready('subscription_events')) {
            return null;
        }

        return [
            'type' => 'bar',
            'heading' => 'Subscription position',
            'rows' => [
                ['label' => 'Active', 'count' => $this->activeSubscriptions()->count()],
                ['label' => 'Pending payments', 'count' => SubscriptionPayment::query()->where('status', PaymentStatus::Pending)->count()],
                ['label' => 'Expiring in 30 days', 'count' => $this->activeSubscriptions()->whereBetween('ends_at', [now(), now()->addDays(30)])->count()],
                ['label' => 'Renewals', 'count' => SubscriptionEvent::query()->where('action', 'renewed')->whereBetween('created_at', [$this->window->from, $this->window->to])->count()],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function countsByDay(Builder $query, string $column): array
    {
        if (! in_array($column, ['created_at', 'completed_at', 'issued_at'], true)) {
            return [];
        }

        return $query
            ->selectRaw('date('.$column.') as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<int, array{label: string, count: int}>
     */
    private function points(array $counts): array
    {
        $start = $this->window->from->startOfDay();
        $end = $this->window->to->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;
        $step = $days > 42 ? 7 : 1;
        $points = [];
        $cursor = $start;

        while ($cursor->lte($end)) {
            $bucketEnd = $cursor->addDays($step - 1)->min($end);
            $total = 0;
            $day = $cursor;

            while ($day->lte($bucketEnd)) {
                $total += (int) ($counts[$day->toDateString()] ?? 0);
                $day = $day->addDay();
            }

            $points[] = [
                'label' => $step === 1 ? $cursor->format('j M') : $cursor->format('j M').'–'.$bucketEnd->format('j M'),
                'count' => $total,
            ];
            $cursor = $bucketEnd->addDay();
        }

        return $points;
    }

    /**
     * @return array{type: string, heading: string, rows: array<int, array{label: string, count: int}>}|null
     */
    private function locationChart(): ?array
    {
        $rows = SourcingRequest::query()
            ->whereBetween('created_at', [$this->window->from, $this->window->to])
            ->whereNotNull('location_label')
            ->where('location_label', '!=', '')
            ->selectRaw('location_label, count(*) as total')
            ->groupBy('location_label')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn (SourcingRequest $request): array => [
                'label' => (string) $request->location_label,
                'count' => (int) $request->total,
            ])
            ->all();

        return $rows === [] ? null : ['type' => 'bar', 'heading' => 'Popular requested locations', 'rows' => $rows];
    }

    /**
     * @return array{type: string, heading: string, unit: string, rows: array<int, array{label: string, count: int}>}|null
     */
    private function financePolygon(): ?array
    {
        if (! $this->ready('commission_agreements') || ! $this->ready('commission_payments') || ! $this->ready('subscription_receipts')) {
            return null;
        }

        return [
            'type' => 'polygon',
            'heading' => 'Commission and premium revenue',
            'unit' => 'KSh',
            'rows' => [
                ['label' => 'Earned', 'count' => $this->commissionAmount(CommissionStatus::Earned)],
                ['label' => 'Payable', 'count' => $this->commissionAmount(CommissionStatus::Payable)],
                ['label' => 'Paid', 'count' => $this->commissionPaid()],
                ['label' => 'Premium receipts', 'count' => $this->premiumReceived()],
            ],
        ];
    }

    private function commissionAmount(CommissionStatus $status): int
    {
        return (int) CommissionAgreement::query()->where('status', $status)->sum('earned_amount');
    }

    private function commissionPaid(): int
    {
        return (int) CommissionPayment::query()
            ->where('status', 'paid')
            ->whereBetween('paid_at', [$this->window->from, $this->window->to])
            ->sum('amount');
    }

    private function premiumReceived(): int
    {
        return (int) SubscriptionReceipt::query()
            ->where('currency', 'KES')
            ->whereBetween('issued_at', [$this->window->from, $this->window->to])
            ->sum('amount');
    }

    private function activeSubscriptions(): Builder
    {
        return Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    /**
     * @param  array<int, VerificationItemState>  $states
     */
    private function propertiesWithStates(array $states): int
    {
        return (int) VerificationItem::query()
            ->whereIn('state', $states)
            ->distinct()
            ->count('property_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function commissions(): array
    {
        if (! $this->ready('commission_agreements') || ! $this->ready('commission_payments')) {
            return $this->unconfigured('Commission records');
        }

        return $this->configured('Commission records', [
            $this->metric('Recorded as earned', $this->money($this->commissionAmount(CommissionStatus::Earned)), null),
            $this->metric('Recorded as payable', $this->money($this->commissionAmount(CommissionStatus::Payable)), null),
            $this->metric('Paid in this period', $this->money($this->commissionPaid()), null),
            $this->metric('Recorded as disputed', $this->money($this->commissionAmount(CommissionStatus::Disputed)), null),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function audits(): array
    {
        $metrics = array_values(array_filter([
            $this->ready('sales_audits') ? $this->metric('Sale events', SalesAudit::query()->whereBetween('created_at', [$this->window->from, $this->window->to])->count(), null) : null,
            $this->ready('property_audits') ? $this->metric('Property events', PropertyAudit::query()->whereBetween('created_at', [$this->window->from, $this->window->to])->count(), null) : null,
            $this->ready('broker_introduction_audits') ? $this->metric('Referral events', BrokerIntroductionAudit::query()->whereBetween('created_at', [$this->window->from, $this->window->to])->count(), null) : null,
            $this->ready('subscription_events') ? $this->metric('Subscription events', SubscriptionEvent::query()->whereBetween('created_at', [$this->window->from, $this->window->to])->count(), null) : null,
            $this->ready('intelligence_audits') ? $this->metric('Research events', IntelligenceAudit::query()->whereBetween('created_at', [$this->window->from, $this->window->to])->count(), null) : null,
        ]));

        if ($metrics === []) {
            return $this->unconfigured('Recorded activity');
        }

        return $this->configured('Recorded activity', $metrics);
    }

    private function money(int $amount): string
    {
        return 'KSh '.number_format($amount);
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function salesTable(): array
    {
        $rows = SalesTransaction::query()
            ->with('property:id,title')
            ->where(function ($query): void {
                $query->where(function ($completed): void {
                    $completed->where('stage', SalesStage::Completed)
                        ->whereBetween('completed_at', [$this->window->from, $this->window->to]);
                })->orWhere(function ($open): void {
                    $open->whereNull('outcome')->where('stage', '!=', SalesStage::Completed);
                });
            })
            ->latest('updated_at')
            ->limit(25)
            ->get()
            ->map(fn (SalesTransaction $sale): array => [
                'cells' => [
                    $sale->reference,
                    $sale->property?->title ?? 'Property removed',
                    $sale->stage->label(),
                    $sale->agreed_price === null ? '—' : $this->money((int) $sale->agreed_price),
                ],
                'href' => $this->saleHref($sale),
            ])
            ->all();

        return [
            'heading' => 'Sales in view',
            'columns' => ['Reference', 'Property', 'Stage', 'Agreed price'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function inventoryTable(): array
    {
        $rows = Property::query()
            ->whereIn('status', [
                PropertyStatus::Published,
                PropertyStatus::Reserved,
                PropertyStatus::Sold,
                PropertyStatus::Review,
            ])
            ->latest('updated_at')
            ->limit(25)
            ->get()
            ->map(fn (Property $property): array => [
                'cells' => [
                    $property->title,
                    $property->locationLabel() !== '' ? $property->locationLabel() : '—',
                    $property->status->label(),
                    $property->availability->label(),
                ],
                'href' => $this->propertyHref($property),
            ])
            ->all();

        return [
            'heading' => 'Listing records',
            'columns' => ['Property', 'Location', 'Status', 'Availability'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function sourcingTable(): array
    {
        $rows = $this->openSourcing()
            ->withCount('matches')
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (SourcingRequest $request): array => [
                'cells' => [
                    $request->reference,
                    $request->location_label ?: '—',
                    $request->status->label(),
                    (string) $request->matches_count,
                ],
                'href' => null,
            ])
            ->all();

        return [
            'heading' => 'Open sourcing requests',
            'columns' => ['Reference', 'Location', 'Status', 'Matches'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function enquiryTable(): array
    {
        $rows = $this->enquiriesInPeriod()
            ->with('property:id,title')
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (Enquiry $enquiry): array => [
                'cells' => [
                    $enquiry->property?->title ?? 'Property removed',
                    $enquiry->created_at?->toFormattedDateString() ?? '—',
                ],
                'href' => $this->user->hasPermission('staff.buyers') ? route('staff.enquiries.index') : null,
            ])
            ->all();

        return [
            'heading' => 'Enquiries in this period',
            'columns' => ['Property', 'Received'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function introductionTable(): array
    {
        $rows = BrokerIntroduction::query()
            ->with('property:id,title')
            ->where('status', BrokerIntroductionStatus::Accepted)
            ->latest('reviewed_at')
            ->limit(25)
            ->get()
            ->map(fn (BrokerIntroduction $introduction): array => [
                'cells' => [
                    $introduction->reference,
                    $introduction->property?->title ?? 'Property removed',
                    $introduction->status->label(),
                ],
                'href' => null,
            ])
            ->all();

        return [
            'heading' => 'Accepted introductions',
            'columns' => ['Reference', 'Property', 'Status'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function referredDealsTable(): array
    {
        $rows = SalesTransaction::query()
            ->with('property:id,title')
            ->whereNotNull('broker_introduction_id')
            ->where(function ($query): void {
                $query->where(function ($open): void {
                    $open->whereNull('outcome')->where('stage', '!=', SalesStage::Completed);
                })->orWhere(function ($completed): void {
                    $completed->where('stage', SalesStage::Completed)
                        ->whereBetween('completed_at', [$this->window->from, $this->window->to]);
                });
            })
            ->latest('updated_at')
            ->limit(25)
            ->get()
            ->map(fn (SalesTransaction $sale): array => [
                'cells' => [
                    $sale->reference,
                    $sale->property?->title ?? 'Property removed',
                    $sale->stage->label(),
                ],
                'href' => $this->saleHref($sale),
            ])
            ->all();

        return [
            'heading' => 'Referred deals',
            'columns' => ['Reference', 'Property', 'Stage'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function subscriberTable(): array
    {
        $rows = $this->activeSubscriptions()
            ->with('user:id,name,email')
            ->latest('ends_at')
            ->limit(25)
            ->get()
            ->map(fn (Subscription $subscription): array => [
                'cells' => [
                    $subscription->user?->name ?? 'Member removed',
                    $subscription->status->label(),
                    $subscription->ends_at?->toFormattedDateString() ?? 'Open ended',
                ],
                'href' => null,
            ])
            ->all();

        return [
            'heading' => 'Active subscribers',
            'columns' => ['Member', 'Status', 'Ends'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function paymentTable(): array
    {
        $rows = SubscriptionPayment::query()
            ->where('status', PaymentStatus::Pending)
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (SubscriptionPayment $payment): array => [
                'cells' => [
                    $payment->checkout_request_id ?: $payment->idempotency_key,
                    $payment->status->label(),
                    $this->money((int) $payment->amount),
                ],
                'href' => null,
            ])
            ->all();

        return [
            'heading' => 'Pending payment attempts',
            'columns' => ['Attempt', 'Status', 'Amount'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function receiptTable(): array
    {
        $rows = SubscriptionReceipt::query()
            ->whereBetween('issued_at', [$this->window->from, $this->window->to])
            ->latest('issued_at')
            ->limit(25)
            ->get()
            ->map(fn (SubscriptionReceipt $receipt): array => [
                'cells' => [
                    $receipt->provider_receipt,
                    $this->money((int) $receipt->amount),
                    $receipt->issued_at->toFormattedDateString(),
                ],
                'href' => null,
            ])
            ->all();

        return [
            'heading' => 'Premium receipts in this period',
            'columns' => ['Receipt', 'Amount', 'Issued'],
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, array{description: string, at: CarbonInterface, href: ?string}>
     */
    private function activityItems(): Collection
    {
        $items = collect();

        if ($this->ready('sales_audits')) {
            $items = $items->concat(
                SalesAudit::query()
                    ->with('transaction:id,reference')
                    ->whereBetween('created_at', [$this->window->from, $this->window->to])
                    ->latest()
                    ->limit(12)
                    ->get()
                    ->map(fn (SalesAudit $audit): array => [
                        'description' => 'Sale '.($audit->transaction?->reference ?? 'record').': '.$audit->event,
                        'at' => $audit->created_at,
                        'href' => $audit->transaction ? $this->saleHref($audit->transaction) : route('admin.oversight.sales', $this->window->query()),
                    ]),
            );
        }

        if ($this->ready('property_audits')) {
            $items = $items->concat(
                PropertyAudit::query()
                    ->with('property:id,title,slug')
                    ->whereBetween('created_at', [$this->window->from, $this->window->to])
                    ->latest()
                    ->limit(12)
                    ->get()
                    ->map(fn (PropertyAudit $audit): array => [
                        'description' => 'Property '.($audit->property?->title ?? 'record').': '.$audit->action,
                        'at' => $audit->created_at,
                        'href' => $audit->property ? $this->propertyHref($audit->property) : route('admin.oversight.inventory', $this->window->query()),
                    ]),
            );
        }

        if ($this->ready('subscription_events')) {
            $items = $items->concat(
                SubscriptionEvent::query()
                    ->with('subscription.user:id,name')
                    ->whereBetween('created_at', [$this->window->from, $this->window->to])
                    ->latest()
                    ->limit(12)
                    ->get()
                    ->map(function (SubscriptionEvent $event): array {
                        $name = $event->subscription?->user?->name;

                        return [
                            'description' => 'Subscription '.$event->action.($name ? ' for '.$name : ''),
                            'at' => $event->created_at,
                            'href' => route('admin.oversight.subscriptions', $this->window->query()),
                        ];
                    }),
            );
        }

        if ($this->ready('subscription_receipts')) {
            $items = $items->concat(
                SubscriptionReceipt::query()
                    ->whereBetween('issued_at', [$this->window->from, $this->window->to])
                    ->latest('issued_at')
                    ->limit(12)
                    ->get()
                    ->map(fn (SubscriptionReceipt $receipt): array => [
                        'description' => 'Premium receipt '.$receipt->provider_receipt.' for '.$this->money((int) $receipt->amount),
                        'at' => $receipt->issued_at,
                        'href' => route('admin.oversight.subscriptions', $this->window->query()),
                    ]),
            );
        }

        if ($this->ready('commission_payments')) {
            $items = $items->concat(
                CommissionPayment::query()
                    ->where('status', 'paid')
                    ->whereBetween('paid_at', [$this->window->from, $this->window->to])
                    ->latest('paid_at')
                    ->limit(12)
                    ->get()
                    ->map(fn (CommissionPayment $payment): array => [
                        'description' => 'Commission '.$payment->reference.' paid, '.$this->money((int) $payment->amount),
                        'at' => $payment->paid_at,
                        'href' => route('admin.oversight.sales', $this->window->query()),
                    ]),
            );
        }

        return $items->filter(fn (array $item): bool => $item['at'] !== null)->values();
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function commissionAgreementTable(): array
    {
        if (! $this->ready('commission_agreements')) {
            return ['heading' => 'Commission agreements', 'columns' => ['Reference', 'Broker', 'Status', 'Recorded amount'], 'rows' => []];
        }

        $rows = CommissionAgreement::query()
            ->with(['broker:id,name', 'transaction:id,reference'])
            ->latest('updated_at')
            ->limit(25)
            ->get()
            ->map(fn (CommissionAgreement $agreement): array => [
                'cells' => [
                    $agreement->reference,
                    $agreement->broker?->name ?? 'Broker removed',
                    $agreement->status->label(),
                    $this->recordedCommission($agreement),
                ],
                'href' => $this->user->hasPermission('staff.finance') ? route('staff.commissions.show', $agreement) : null,
            ])
            ->all();

        return [
            'heading' => 'Commission agreements',
            'columns' => ['Reference', 'Broker', 'Status', 'Recorded amount'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function commissionPaymentTable(): array
    {
        if (! $this->ready('commission_payments')) {
            return ['heading' => 'Commission payments', 'columns' => ['Reference', 'Amount', 'Status', 'Paid'], 'rows' => []];
        }

        $rows = CommissionPayment::query()
            ->where(function ($query): void {
                $query->whereBetween('paid_at', [$this->window->from, $this->window->to])
                    ->orWhereBetween('created_at', [$this->window->from, $this->window->to]);
            })
            ->latest('updated_at')
            ->limit(25)
            ->get()
            ->map(fn (CommissionPayment $payment): array => [
                'cells' => [
                    $payment->reference,
                    $this->money((int) $payment->amount),
                    (string) $payment->status,
                    $payment->paid_at?->toFormattedDateString() ?? 'Not paid',
                ],
                'href' => null,
            ])
            ->all();

        return [
            'heading' => 'Commission payments in this period',
            'columns' => ['Reference', 'Amount', 'Status', 'Paid'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function completedSaleValueTable(): array
    {
        if (! $this->ready('sales_transactions')) {
            return ['heading' => 'Completed sales', 'columns' => ['Reference', 'Property', 'Agreed price', 'Completed'], 'rows' => []];
        }

        $rows = $this->completedSales()
            ->with('property:id,title')
            ->latest('completed_at')
            ->limit(25)
            ->get()
            ->map(fn (SalesTransaction $sale): array => [
                'cells' => [
                    $sale->reference,
                    $sale->property?->title ?? 'Property removed',
                    $sale->agreed_price === null ? '—' : $this->money((int) $sale->agreed_price),
                    $sale->completed_at?->toFormattedDateString() ?? '—',
                ],
                'href' => $this->saleHref($sale),
            ])
            ->all();

        return [
            'heading' => 'Completed sales in this period',
            'columns' => ['Reference', 'Property', 'Agreed price', 'Completed'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{heading: string, columns: array<int, string>, rows: array<int, array{cells: array<int, string>, href: ?string}>}
     */
    private function auditTable(): array
    {
        $rows = $this->auditRows()
            ->sortByDesc(fn (array $row): int => $row['at']->getTimestamp())
            ->take(40)
            ->map(fn (array $row): array => [
                'cells' => [
                    $row['at']->toDayDateTimeString(),
                    $row['record'],
                    $row['event'],
                    $row['actor'],
                ],
                'href' => $row['href'],
            ])
            ->values()
            ->all();

        return [
            'heading' => 'Events in this period',
            'columns' => ['When', 'Record', 'Event', 'Actor'],
            'rows' => $rows,
        ];
    }

    /**
     * @return Collection<int, array{at: CarbonInterface, record: string, event: string, actor: string, href: ?string}>
     */
    private function auditRows(): Collection
    {
        $rows = collect();

        if ($this->ready('sales_audits')) {
            $rows = $rows->concat(
                SalesAudit::query()->with(['transaction:id,reference', 'actor:id,name'])->whereBetween('created_at', [$this->window->from, $this->window->to])->latest()->limit(20)->get()
                    ->map(fn (SalesAudit $audit): array => [
                        'at' => $audit->created_at,
                        'record' => 'Sale '.($audit->transaction?->reference ?? 'record'),
                        'event' => (string) $audit->event,
                        'actor' => $audit->actor?->name ?? 'System',
                        'href' => $audit->transaction ? $this->saleHref($audit->transaction) : null,
                    ]),
            );
        }

        if ($this->ready('property_audits')) {
            $rows = $rows->concat(
                PropertyAudit::query()->with(['property:id,title,slug', 'actor:id,name'])->whereBetween('created_at', [$this->window->from, $this->window->to])->latest()->limit(20)->get()
                    ->map(fn (PropertyAudit $audit): array => [
                        'at' => $audit->created_at,
                        'record' => $audit->property?->title ?? 'Property record',
                        'event' => (string) $audit->action,
                        'actor' => $audit->actor?->name ?? 'System',
                        'href' => $audit->property ? $this->propertyHref($audit->property) : null,
                    ]),
            );
        }

        if ($this->ready('broker_introduction_audits')) {
            $rows = $rows->concat(
                BrokerIntroductionAudit::query()->with(['introduction:id,reference', 'actor:id,name'])->whereBetween('created_at', [$this->window->from, $this->window->to])->latest()->limit(20)->get()
                    ->map(fn (BrokerIntroductionAudit $audit): array => [
                        'at' => $audit->created_at,
                        'record' => 'Referral '.($audit->introduction?->reference ?? 'record'),
                        'event' => (string) $audit->action,
                        'actor' => $audit->actor?->name ?? 'System',
                        'href' => null,
                    ]),
            );
        }

        if ($this->ready('subscription_events')) {
            $rows = $rows->concat(
                SubscriptionEvent::query()->with(['actor:id,name', 'subscription.user:id,name'])->whereBetween('created_at', [$this->window->from, $this->window->to])->latest()->limit(20)->get()
                    ->map(fn (SubscriptionEvent $event): array => [
                        'at' => $event->created_at,
                        'record' => 'Subscription '.($event->subscription?->user?->name ?? 'member'),
                        'event' => (string) $event->action,
                        'actor' => $event->actor?->name ?? 'System',
                        'href' => route('admin.oversight.subscriptions', $this->window->query()),
                    ]),
            );
        }

        if ($this->ready('intelligence_audits')) {
            $rows = $rows->concat(
                IntelligenceAudit::query()->with('actor:id,name')->whereBetween('created_at', [$this->window->from, $this->window->to])->latest()->limit(20)->get()
                    ->map(fn (IntelligenceAudit $audit): array => [
                        'at' => $audit->created_at,
                        'record' => 'Research record',
                        'event' => (string) $audit->action,
                        'actor' => $audit->actor?->name ?? 'System',
                        'href' => null,
                    ]),
            );
        }

        return $rows->filter(fn (array $row): bool => $row['at'] !== null)->values();
    }

    private function recordedCommission(CommissionAgreement $agreement): string
    {
        if ($agreement->earned_amount !== null) {
            return $this->money((int) $agreement->earned_amount);
        }

        if ($agreement->fixed_amount !== null) {
            return $this->money((int) $agreement->fixed_amount).' fixed';
        }

        if ($agreement->rate_percent !== null) {
            return rtrim(rtrim(number_format((float) $agreement->rate_percent, 2), '0'), '.').'%';
        }

        return 'Not recorded';
    }

    private function saleHref(SalesTransaction $sale): string
    {
        if ($this->user->hasPermission('staff.sales')) {
            return route('staff.sales.show', $sale);
        }

        return route('admin.oversight.sales', $this->window->query());
    }

    private function propertyHref(Property $property): string
    {
        if ($this->user->hasPermission('staff.properties')) {
            return route('staff.properties.show', $property);
        }

        return route('admin.oversight.inventory', $this->window->query());
    }

    private function ready(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
