<?php

namespace App\Services\Communications;

use App\Enums\VerificationCategory;
use App\Enums\Workspace;
use App\Models\CapabilityApplication;
use App\Models\CommissionAgreement;
use App\Models\DevelopmentProject;
use App\Models\DocumentAccessRequest;
use App\Models\Enquiry;
use App\Models\Offer;
use App\Models\Property;
use App\Models\PropertyInformationRequest;
use App\Models\ResearchReport;
use App\Models\SalesTransaction;
use App\Models\SiteVisit;
use App\Models\SourcingRequest;
use App\Models\User;
use App\Models\VerificationItem;
use App\Models\VerificationPassport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class NotificationInbox
{
    public function attention(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user): void {
            $query->whereRaw('1 = 0');
            $rules = [
                ['ListingStatusNotification', 'property_id', Property::query()->where('submitted_by', $user->id)->where('status', 'draft')],
                ['ApplicationOutcomeNotification', 'application_id', CapabilityApplication::query()->where('user_id', $user->id)->where('status', 'information_required')],
                ['PropertyInformationRequestedNotification', 'information_request_id', PropertyInformationRequest::query()->whereNull('responded_at')->whereHas('property', fn (Builder $q) => $q->where('submitted_by', $user->id))],
            ];
            if ($user->hasPermission('staff.brokerage')) {
                $rules[] = ['ApplicationSubmittedNotification', 'application_id', CapabilityApplication::query()->where('user_id', '!=', $user->id)->where('status', 'pending')];
            }
            if ($user->hasPermission('staff.properties')) {
                $rules[] = ['PropertySubmittedNotification', 'property_id', Property::query()->where('status', 'review')];
            }
            if ($user->canAccessWorkspace(Workspace::Staff)) {
                foreach (['EnquirySubmittedNotification', 'EnquiryAssignedNotification'] as $type) {
                    $rules[] = [$type, 'enquiry_id', Enquiry::query()->visibleToStaff($user)->whereIn('status', ['new', 'acknowledged', 'in_progress'])];
                }
                $rules[] = ['SourcingAssignedNotification', 'sourcing_request_id', SourcingRequest::query()->where('assigned_to', $user->id)->whereIn('status', ['submitted', 'in_progress'])];
                $rules[] = ['SiteVisitUpdatedNotification', 'site_visit_id', SiteVisit::query()->visibleToStaff($user)->whereIn('status', ['requested', 'confirmed'])];
            }
            if ($user->hasPermission('staff.buyers')) {
                $rules[] = ['DocumentAccessDecidedNotification', 'document_access_request_id', DocumentAccessRequest::query()->where('status', 'requested')];
            }
            if ($user->hasPermission('staff.sales') || $user->hasPermission('staff.buyers')) {
                $rules[] = ['OfferSubmittedNotification', 'offer_id', Offer::query()->whereIn('status', ['submitted', 'under_review'])];
            }
            if ($user->hasPermission('staff.sales')) {
                $query->orWhere(fn (Builder $q) => $q->where('type', 'App\\Notifications\\WorkAssignedNotification')->where('data->kind', 'Sales case')->whereIn('data->record_id', SalesTransaction::query()->select('id')->where('assigned_to', $user->id)->whereNull('outcome')->where('stage', '!=', 'completed')));
            }
            if ($user->hasPermission('staff.verification')) {
                $critical = array_map(fn ($category) => $category->value, array_filter(VerificationCategory::cases(), fn ($category) => $category->critical()));
                $query->orWhere(fn (Builder $q) => $q->where('type', 'App\\Notifications\\WorkAssignedNotification')->where('data->kind', 'Verification review')->whereIn('data->record_id', VerificationPassport::query()->select('id')->where('assigned_to', $user->id)->whereHas('property.verificationItems', fn (Builder $items) => $items->whereIn('category', $critical)->whereIn('state', ['not_supplied', 'received', 'under_review', 'failed', 'expired']))));
                $rules[] = ['ReverificationDueNotification', 'verification_item_id', VerificationItem::query()->where(function (Builder $q): void {
                    $q->where('state', 'expired')->orWhere(fn (Builder $due) => $due->where('state', 'verified')->where(fn (Builder $dates) => $dates->where('expires_at', '<=', now())->orWhere('review_due_at', '<=', now())));
                })];
            }
            foreach ($rules as [$type, $key, $records]) {
                $query->orWhere(fn (Builder $q) => $q->where('type', 'App\\Notifications\\'.$type)->whereIn('data->'.$key, $records->select('id')));
            }
        });
    }

    public function needsAttention(DatabaseNotification $notification, User $user): bool
    {
        return $this->attention($user->notifications()->getQuery(), $user)->whereKey($notification->id)->exists();
    }

    public function category(DatabaseNotification $notification, User $user): string
    {
        return $this->needsAttention($notification, $user) ? 'Needs attention' : (str_contains($notification->type, 'Response') ? 'Message' : 'Update');
    }

    public function destination(DatabaseNotification $notification, User $user): ?string
    {
        $path = $this->safePath($notification->data['url'] ?? null);
        if ($path === null) {
            return null;
        }
        $data = $notification->data;
        if (str_ends_with($notification->type, '\\DocumentAccessDecidedNotification')) {
            $record = DocumentAccessRequest::query()->find($data['document_access_request_id'] ?? null);
            if ($record === null || ! $user->can('view', $record)) {
                return null;
            }
            $path = (int) $record->buyer_id === (int) $user->id
                ? ($record->property_match_id ? route('buyer.matches.show', $record->property_match_id, false) : route('buyer.matches.index', [], false))
                : route('staff.documents.index', ['record' => $record->id], false).'#document-'.$record->id;
        } elseif (str_ends_with($notification->type, '\\SiteVisitUpdatedNotification')) {
            $record = SiteVisit::query()->find($data['site_visit_id'] ?? null);
            if ($record === null || ! $user->can('view', $record)) {
                return null;
            }
            $path = (int) $record->buyer_id === (int) $user->id
                ? route('buyer.matches.show', $record->property_match_id, false)
                : route('staff.visits.index', ['record' => $record->id], false).'#visit-'.$record->id;
        } elseif (str_ends_with($notification->type, '\\OfferSubmittedNotification') || str_ends_with($notification->type, '\\OfferStatusNotification')) {
            $record = Offer::query()->find($data['offer_id'] ?? null);
            if ($record === null || ! $user->can('view', $record)) {
                return null;
            }
            $path = route((int) $record->buyer_id === (int) $user->id ? 'buyer.offers.index' : 'staff.offers.index', ['record' => $record->id], false).'#offer-'.$record->id;
        }
        try {
            $router = app('router');
            $route = $router->getRoutes()->match(Request::create($path, 'GET'));
            $name = (string) $route->getName();
            $workspace = Workspace::tryFrom(strstr($name, '.', true) ?: '');
            if ($workspace !== null && ! $user->canAccessWorkspace($workspace)) {
                return null;
            }
            if (! preg_match('/^(buyer|seller|broker|staff|admin|investor)\./', $name)
                && ! in_array($name, ['home', 'workspaces.index', 'account.applications.index', 'account.applications.create'], true)) {
                return null;
            }
            if (str_contains($name, '.notifications.') || str_contains($name, '.files.') || str_ends_with($name, '.download')) {
                return null;
            }
            foreach ($route->gatherMiddleware() as $middleware) {
                if (str_starts_with($middleware, 'permission:') && ! $user->hasPermission(substr($middleware, 11))) {
                    return null;
                }
            }
            $router->substituteBindings($route);
            $router->substituteImplicitBindings($route);
            $recordClass = match ($name) {
                'staff.offers.index', 'buyer.offers.index' => Offer::class,
                'staff.documents.index' => DocumentAccessRequest::class,
                'staff.visits.index' => SiteVisit::class,
                default => null,
            };
            parse_str(parse_url($path, PHP_URL_QUERY) ?? '', $parameters);
            if ($recordClass !== null && isset($parameters['record'])) {
                $record = $recordClass::query()->find($parameters['record']);
                if ($record === null || ! $user->can('view', $record)) {
                    return null;
                }
            }
            foreach ($route->parameters() as $record) {
                if (! $record instanceof Model) {
                    continue;
                }
                $ability = $record instanceof Property && preg_match('/^(seller|broker)\./', $name) ? 'viewSubmission' : 'view';
                if ($record instanceof CapabilityApplication && str_starts_with($name, 'staff.')) {
                    $ability = 'decide';
                }
                if ($record instanceof SalesTransaction && $name === 'staff.sales.show') {
                    if (! $user->hasPermission('staff.sales')) {
                        return null;
                    }

                    continue;
                }
                if ($record instanceof SalesTransaction && $name === 'broker.deals.show') {
                    $introduction = $record->introduction;
                    if ($introduction === null || (int) $introduction->broker_id !== (int) $user->id || ! in_array($introduction->status->value, ['accepted', 'active'], true)) {
                        return null;
                    }

                    continue;
                }
                if (($record instanceof ResearchReport || $record instanceof DevelopmentProject) && str_starts_with($name, 'investor.')) {
                    if (! $user->hasActiveInvestorPass() || ! $record->isReleased()) {
                        return null;
                    }

                    continue;
                }
                if ($record instanceof CommissionAgreement && $name === 'broker.agreements.show') {
                    if ((int) $record->broker_id !== (int) $user->id || ! in_array($record->status->value, ['accepted', 'earned', 'payable', 'paid', 'disputed'], true)) {
                        return null;
                    }

                    continue;
                }
                if ($record instanceof Property && str_starts_with($name, 'staff.verification.')) {
                    if (! $user->hasPermission('staff.verification')) {
                        return null;
                    }

                    continue;
                }
                if (! Gate::forUser($user)->allows($ability, $record)) {
                    return null;
                }
            }
            $permission = match ($name) {
                'staff.documents.index' => $user->hasPermission('staff.buyers'),
                'staff.offers.index' => $user->hasPermission('staff.buyers') || $user->hasPermission('staff.sales'),
                'staff.visits.index' => $user->can('viewAny', SiteVisit::class),
                default => true,
            };

            return $permission ? $path : null;
        } catch (HttpExceptionInterface|ModelNotFoundException|\InvalidArgumentException|\ValueError $exception) {
            return null;
        }
    }

    public function safePath(mixed $url): ?string
    {
        if (! is_string($url) || $url === '' || preg_match('/[\x00-\x20\\\\]/', rawurldecode($url))) {
            return null;
        }
        $parts = parse_url($url);
        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true))) {
            return null;
        }
        $path = $parts['path'] ?? '';
        if (! str_starts_with($path, '/') || str_starts_with(rawurldecode($path), '//')) {
            return null;
        }
        if (isset($parts['host'])) {
            $host = strtolower($parts['host']);
            $appHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
            $loopback = in_array($appHost, ['localhost', '127.0.0.1'], true) && in_array($host, ['localhost', '127.0.0.1'], true);
            if ((! $loopback && $host !== $appHost) || ($parts['port'] ?? null) !== parse_url(config('app.url'), PHP_URL_PORT)) {
                return null;
            }
        }
        parse_str($parts['query'] ?? '', $query);
        $query = array_filter(array_intersect_key($query, array_flip(['tab', 'page', 'view', 'capability', 'record'])), fn ($value): bool => is_string($value) && preg_match('/^[a-zA-Z0-9_-]{1,80}$/', $value));

        return $path.($query === [] ? '' : '?'.http_build_query($query)).(isset($parts['fragment']) && preg_match('/^[a-zA-Z0-9_-]+$/', $parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
