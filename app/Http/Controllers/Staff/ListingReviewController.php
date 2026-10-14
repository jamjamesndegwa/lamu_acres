<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Services\Broker\DuplicateListingDetector;
use App\Services\Broker\PropertySubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingReviewController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage', Property::class);

        return view('staff.listings.index', [
            'listings' => Property::query()
                ->whereNotNull('submitted_by')
                ->where('status', '!=', PropertyStatus::Published)
                ->with('submitter:id,name')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function show(Property $property, DuplicateListingDetector $duplicates): View
    {
        $this->authorize('reviewSubmission', $property);

        $property->load(['submitter', 'media', 'informationRequests.staff', 'audits']);

        return view('staff.listings.show', [
            'listing' => $property,
            'matches' => $duplicates->staffMatches($property),
            'blockers' => app(\App\Services\Verification\VerificationOperations::class)->blockers($property),
        ]);
    }

    public function requestInformation(Request $request, Property $property, PropertySubmission $submissions): RedirectResponse
    {
        $this->authorize('reviewSubmission', $property);

        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
        ]);

        $submissions->requestInformation($request->user(), $property, $data['question']);

        return back()->with('status', 'The broker was asked for more information.');
    }

    public function approve(Request $request, Property $property, PropertySubmission $submissions): RedirectResponse
    {
        $this->authorize('reviewSubmission', $property);
        abort_if($property->status === PropertyStatus::Published, 422);

        $submissions->approve($request->user(), $property);

        return back()->with('status', 'The listing is published.');
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
