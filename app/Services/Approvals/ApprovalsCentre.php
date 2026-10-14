<?php

namespace App\Services\Approvals;

use App\Enums\AuthorityStatus;
use App\Enums\BrokerIntroductionStatus;
use App\Enums\CapabilityApplicationStatus;
use App\Enums\CommissionStatus;
use App\Enums\DocumentAccessStatus;
use App\Enums\PropertyStatus;
use App\Enums\PublicationStatus;
use App\Enums\Workspace;
use App\Models\BrokerIntroduction;
use App\Models\CapabilityApplication;
use App\Models\CommissionAgreement;
use App\Models\DevelopmentProject;
use App\Models\DocumentAccessRequest;
use App\Models\ImageryComparison;
use App\Models\ImageryScene;
use App\Models\MarketingAuthority;
use App\Models\PlanningRecord;
use App\Models\Property;
use App\Models\ResearchReport;
use App\Models\SourcingMatch;
use App\Models\User;
use App\Models\VerifiedSale;
use App\Services\Verification\VerificationOperations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ApprovalsCentre
{
    public function __construct(private VerificationOperations $verification) {}

    /**
     * @return array{
     *     tab: string,
     *     tabs: array<int, array{key: string, label: string, count: int}>,
     *     rows: LengthAwarePaginator,
     *     statuses: array<int, string>,
     *     gaps: array<int, string>,
     *     redirect: ?string
     * }
     */
    public function present(User $user, string $tab, string $search, string $status): array
    {
        $categories = $this->categories($user);
        $allowed = array_merge(['all'], array_column($categories, 'key'));

        if (! in_array($tab, ['all', 'listings', 'applications', 'verification', 'commissions', 'intelligence', 'referrals', 'sourcing', 'authorities', 'documents'], true) || ! in_array($tab, $allowed, true)) {
            return [
                'tab' => 'all',
                'tabs' => [],
                'rows' => $this->paginate(collect()),
                'statuses' => [],
                'gaps' => [],
                'redirect' => 'That approval category is not open for this account.',
            ];
        }

        $grouped = collect($categories)->mapWithKeys(
            fn (array $category): array => [$category['key'] => $this->rowsFor($category['key'])],
        );

        $counts = $grouped->map(fn (Collection $rows): int => $rows->count());
        $pool = $tab === 'all'
            ? $grouped->flatten(1)
            : $grouped->get($tab, collect());

        $statuses = $pool->pluck('status')->unique()->sort()->values()->all();
        $filtered = $pool
            ->when($status !== '', fn (Collection $rows) => $rows->where('status', $status))
            ->when($search !== '', function (Collection $rows) use ($search): Collection {
                $needle = mb_strtolower($search);

                return $rows->filter(function (array $row) use ($needle): bool {
                    $haystack = mb_strtolower(implode(' ', [
                        $row['reference'],
                        $row['type'],
                        $row['submitted_by'],
                        $row['status'],
                    ]));

                    return str_contains($haystack, $needle);
                })->values();
            })
            ->sortByDesc(fn (array $row) => $row['submitted_at']?->getTimestamp() ?? 0)
            ->values();

        return [
            'tab' => $tab,
            'tabs' => array_merge(
                [['key' => 'all', 'label' => 'All Pending', 'count' => (int) $counts->sum()]],
                array_map(fn (array $category): array => [
                    'key' => $category['key'],
                    'label' => $category['label'],
                    'count' => (int) $counts->get($category['key'], 0),
                ], $categories),
            ),
            'rows' => $this->paginate($filtered),
            'statuses' => $statuses,
            'gaps' => $this->gaps($user),
            'redirect' => null,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function categories(User $user): array
    {
        return array_values(array_filter([
            $this->open($user, 'listings', 'Property Listings', 'staff.properties'),
            $this->open($user, 'applications', 'Broker/Seller Applications', 'staff.brokerage'),
            $this->open($user, 'referrals', 'Broker Referrals', 'staff.brokerage'),
            $this->open($user, 'authorities', 'Marketing Authorities', 'staff.properties'),
            $this->open($user, 'verification', 'Verification Exceptions', 'staff.verification'),
            $this->open($user, 'sourcing', 'Sourcing Matches', 'staff.sourcing'),
            $this->open($user, 'documents', 'Document Access', 'staff.buyers'),
            $this->commissionCategory($user),
            $this->open($user, 'intelligence', 'Intelligence Publications', 'intelligence.publish'),
        ]));
    }

    /**
     * @return array{key: string, label: string}|null
     */
    private function open(User $user, string $key, string $label, string $permission): ?array
    {
        if (! $user->canAccessWorkspace(Workspace::Staff) || ! $user->hasPermission($permission)) {
            return null;
        }

        return ['key' => $key, 'label' => $label];
    }

    /**
     * @return array{key: string, label: string}|null
     */
    private function commissionCategory(User $user): ?array
    {
        if (! $user->canAccessWorkspace(Workspace::Staff) || ! $user->hasPermission('commissions.approve') || ! $user->hasPermission('staff.finance')) {
            return null;
        }

        return ['key' => 'commissions', 'label' => 'Commission Decisions'];
    }

    /**
     * @return array<int, string>
     */
    private function gaps(User $user): array
    {
        $gaps = [];

        if ($user->hasPermission('commissions.approve') && (! $user->canAccessWorkspace(Workspace::Staff) || ! $user->hasPermission('staff.finance'))) {
            $gaps[] = 'Commission authorization is granted, but the existing payable approval route stays on the finance settlement desk. This queue stays hidden until that desk is open for this account.';
        }

        if ($user->canAccessWorkspace(Workspace::Staff) && $user->hasPermission('intelligence.publish')) {
            $gaps[] = 'A verified sale in review has no detail page. Its publication control remains on the price desk list, so this queue does not offer a publish button for it.';
        }

        return $gaps;
    }

    /**
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function rowsFor(string $key): Collection
    {
        return match ($key) {
            'listings' => $this->listings(),
            'applications' => $this->applications(),
            'referrals' => $this->referrals(),
            'authorities' => $this->authorities(),
            'verification' => $this->exceptions(),
            'sourcing' => $this->sourcingMatches(),
            'documents' => $this->documents(),
            'commissions' => $this->commissions(),
            'intelligence' => $this->publications(),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function listings(): Collection
    {
        return Property::query()
            ->whereNotNull('submitted_by')
            ->where('status', '!=', PropertyStatus::Published)
            ->with('submitter:id,name')
            ->withExists(['informationRequests as returned' => fn ($query) => $query->whereNull('responded_at')])
            ->latest()
            ->get()
            ->map(fn (Property $property): array => $this->row(
                $property->reference,
                'Property listing',
                $property->submitter?->name ?: 'Not recorded',
                $property->created_at,
                $property->returned ? 'Returned for information' : $property->status->label(),
                route('staff.listings.show', ['property' => $property, 'return' => 'approvals']),
                'Review and publish',
            ));
    }

    /**
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function applications(): Collection
    {
        return CapabilityApplication::query()
            ->with('user:id,name')
            ->whereIn('status', [CapabilityApplicationStatus::Pending, CapabilityApplicationStatus::InformationRequired])
            ->latest()
            ->get()
            ->map(fn (CapabilityApplication $application): array => $this->row(
                'APP-'.$application->id,
                $application->capability->label().' application',
                $application->user?->name ?: 'Not recorded',
                $application->created_at,
                $application->status->label(),
                route('staff.applications.edit', ['application' => $application, 'return' => 'approvals']),
                'Record decision',
            ));
    }

    /**
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function referrals(): Collection
    {
        return BrokerIntroduction::query()
            ->with('broker:id,name')
            ->whereIn('status', [
                BrokerIntroductionStatus::Submitted,
                BrokerIntroductionStatus::Review,
                BrokerIntroductionStatus::Disputed,
            ])
            ->latest('submitted_at')
            ->latest()
            ->get()
            ->map(fn (BrokerIntroduction $referral): array => $this->row(
                $referral->reference,
                'Broker referral',
                $referral->broker?->name ?: 'Not recorded',
                $referral->submitted_at ?? $referral->created_at,
                $referral->status->label(),
                route('staff.referrals.show', ['referral' => $referral, 'return' => 'approvals']),
                'Record decision',
            ));
    }

    /**
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function authorities(): Collection
    {
        return MarketingAuthority::query()
            ->with(['property:id,slug,reference', 'seller:id,name'])
            ->where('status', AuthorityStatus::Submitted)
            ->latest('submitted_at')
            ->latest()
            ->get()
            ->map(fn (MarketingAuthority $authority): array => $this->row(
                $authority->property?->reference ?: 'Authority '.$authority->id,
                'Marketing authority',
                $authority->seller?->name ?: 'Not recorded',
                $authority->submitted_at ?? $authority->created_at,
                $authority->status->label(),
                $authority->property
                    ? route('staff.properties.show', ['property' => $authority->property, 'return' => 'approvals'])
                    : route('staff.authorities.index'),
                'Record authority decision',
            ));
    }

    /**
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function sourcingMatches(): Collection
    {
        return SourcingMatch::query()
            ->with(['sourcingRequest.buyer:id,name', 'property:id,reference,slug'])
            ->whereNull('dismissed_at')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('property_matches')
                    ->whereColumn('property_matches.property_id', 'sourcing_matches.property_id')
                    ->whereColumn('property_matches.sourcing_request_id', 'sourcing_matches.sourcing_request_id')
                    ->whereNotNull('property_matches.approved_at');
            })
            ->latest()
            ->get()
            ->filter(fn (SourcingMatch $match): bool => $match->sourcingRequest !== null && $match->property !== null)
            ->map(fn (SourcingMatch $match): array => $this->row(
                $match->sourcingRequest->reference.' · '.$match->property->reference,
                'Sourcing match',
                $match->sourcingRequest->buyer?->name ?: 'Not recorded',
                $match->created_at,
                $match->band->label(),
                route('staff.sourcing.show', ['sourcingRequest' => $match->sourcingRequest, 'return' => 'approvals']),
                'Review match',
            ))
            ->values();
    }

    /**
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function documents(): Collection
    {
        return DocumentAccessRequest::query()
            ->with(['buyer:id,name', 'property:id,reference'])
            ->where('status', DocumentAccessStatus::Requested)
            ->latest()
            ->get()
            ->map(fn (DocumentAccessRequest $request): array => $this->row(
                $request->property?->reference ?: 'Document '.$request->id,
                'Document access',
                $request->buyer?->name ?: 'Not recorded',
                $request->created_at,
                $request->status->label(),
                route('staff.documents.index', ['return' => 'approvals']),
                'Grant or decline',
            ));
    }

    /**
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function exceptions(): Collection
    {
        return $this->verification->exceptions()
            ->with('submitter:id,name')
            ->latest()
            ->get()
            ->map(fn (Property $property): array => $this->row(
                $property->reference,
                'Verification exception',
                $property->submitter?->name ?: 'Not recorded',
                $property->created_at,
                'Exception',
                route('staff.verification.show', ['property' => $property, 'return' => 'approvals']),
                'Review exception',
            ));
    }

    /**
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function commissions(): Collection
    {
        return CommissionAgreement::query()
            ->with('broker:id,name')
            ->where('status', CommissionStatus::Earned)
            ->latest()
            ->get()
            ->map(fn (CommissionAgreement $agreement): array => $this->row(
                $agreement->reference,
                'Commission decision',
                $agreement->broker?->name ?: 'Not recorded',
                $agreement->created_at,
                $agreement->status->label(),
                route('staff.commissions.show', ['commissionAgreement' => $agreement, 'return' => 'approvals']),
                'Approve as payable',
            ));
    }

    /**
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function publications(): Collection
    {
        $review = PublicationStatus::Review;

        return collect()
            ->concat($this->publicationRows(DevelopmentProject::class, 'Development project', 'staff.projects.show', 'project', $review))
            ->concat($this->publicationRows(ImageryScene::class, 'Imagery scene', 'staff.imagery.scenes.edit', 'scene', $review))
            ->concat($this->publicationRows(ImageryComparison::class, 'Imagery comparison', 'staff.imagery.comparisons.show', 'comparison', $review))
            ->concat($this->publicationRows(PlanningRecord::class, 'Planning record', 'staff.planning.show', 'record', $review))
            ->concat($this->publicationRows(ResearchReport::class, 'Research report', 'staff.reports.show', 'report', $review))
            ->concat($this->saleRows())
            ->values();
    }

    /**
     * @param  class-string<Model>  $model
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function publicationRows(string $model, string $type, string $route, string $parameter, PublicationStatus $status): Collection
    {
        return $model::query()
            ->with(['author:id,name', 'audits'])
            ->where('publication_status', $status)
            ->latest()
            ->get()
            ->map(function (Model $record) use ($type, $route, $parameter): array {
                $submitted = $record->audits->where('action', 'submitted')->sortByDesc('created_at')->first();

                return $this->row(
                    (string) $record->getAttribute('reference'),
                    $type,
                    $record->author?->name ?: 'Not recorded',
                    $submitted?->created_at ?? $record->getAttribute('created_at'),
                    $record->publication_status->label(),
                    route($route, [$parameter => $record, 'return' => 'approvals']),
                    'Review and publish',
                );
            });
    }

    /**
     * @return Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>
     */
    private function saleRows(): Collection
    {
        return VerifiedSale::query()
            ->with(['author:id,name', 'audits'])
            ->where('publication_status', PublicationStatus::Review)
            ->latest()
            ->get()
            ->map(function (VerifiedSale $sale): array {
                $submitted = $sale->audits->where('action', 'submitted')->sortByDesc('created_at')->first();

                return $this->row(
                    $sale->reference,
                    'Verified sale',
                    $sale->author?->name ?: 'Not recorded',
                    $submitted?->created_at ?? $sale->created_at,
                    $sale->publication_status->label(),
                    route('staff.prices.index'),
                    null,
                );
            });
    }

    /**
     * @return array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}
     */
    private function row(string $reference, string $type, string $submittedBy, mixed $submittedAt, string $status, string $detailsUrl, ?string $decision): array
    {
        return [
            'reference' => $reference,
            'type' => $type,
            'submitted_by' => $submittedBy,
            'submitted_at' => $submittedAt instanceof Carbon ? $submittedAt : ($submittedAt ? Carbon::parse($submittedAt) : null),
            'status' => $status,
            'details_url' => $detailsUrl,
            'decision' => $decision,
        ];
    }

    /**
     * @param  Collection<int, array{reference: string, type: string, submitted_by: string, submitted_at: ?Carbon, status: string, details_url: string, decision: ?string}>  $rows
     */
    private function paginate(Collection $rows): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();
        $perPage = 20;

        return new Paginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => route('admin.approvals.index'), 'query' => request()->query()],
        );
    }
}
