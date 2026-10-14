<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Services\Broker\DuplicateListingDetector;
use App\Services\Broker\PropertySubmission;
use App\Services\Verification\VerificationOperations;
use App\Support\ApprovalsReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingReviewController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage', Property::class);
        $queue = $request->query('queue');

        return view('staff.listings.index', [
            'queue' => in_array($queue, ['pending', 'returned'], true) ? $queue : 'all',
            'listings' => Property::query()
                ->whereNotNull('submitted_by')
                ->where('status', '!=', PropertyStatus::Published)
                ->with('submitter:id,name')
                ->withExists(['informationRequests as returned' => fn ($query) => $query->whereNull('responded_at')])
                ->when($queue === 'pending', fn ($query) => $query->whereDoesntHave('informationRequests', fn ($inner) => $inner->whereNull('responded_at')))
                ->when($queue === 'returned', fn ($query) => $query->whereHas('informationRequests', fn ($inner) => $inner->whereNull('responded_at')))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function show(Property $property, DuplicateListingDetector $duplicates): View
    {
        $this->authorize('reviewSubmission', $property);

        $property->load(['submitter', 'media', 'informationRequests.staff', 'audits']);

        return view('staff.listings.show', [
            'listing' => $property,
            'matches' => $duplicates->staffMatches($property),
            'blockers' => app(VerificationOperations::class)->blockers($property),
        ]);
    }

    public function requestInformation(Request $request, Property $property, PropertySubmission $submissions): RedirectResponse
    {
        $this->authorize('reviewSubmission', $property);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
        ]);

        $submissions->requestInformation($request->user(), $property, $data['question']);

        return ApprovalsReturn::back($request, 'listings', 'The broker was asked for more information.');
    }

    public function approve(Request $request, Property $property, PropertySubmission $submissions): RedirectResponse
    {
        $this->authorize('reviewSubmission', $property);
        abort_if($property->status === PropertyStatus::Published, 422);

        $submissions->approve($request->user(), $property);

        return ApprovalsReturn::back($request, 'listings', 'The listing is published.');
    }

    public function media(Property $property, PropertyMedia $propertyMedia): StreamedResponse
    {
        $this->authorize('reviewSubmission', $property);
        abort_unless($propertyMedia->property_id === $property->id, 404);
        abort_unless(str_starts_with($propertyMedia->path, 'broker-listings/'.$property->id.'/'), 404);

        $name = basename(str_replace('\\', '/', $propertyMedia->original_name));

        return Storage::disk($propertyMedia->disk)->download($propertyMedia->path, $name === '' ? 'file' : $name);
    }
}
