<?php

namespace App\Http\Controllers\Broker;

use App\Enums\LandUse;
use App\Enums\ListingPurpose;
use App\Enums\MarketingRole;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Enums\SaleMode;
use App\Http\Controllers\Controller;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyInformationRequest;
use App\Models\PropertyMedia;
use App\Services\Broker\DuplicateListingDetector;
use App\Services\Broker\PropertySubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ListingController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('submit', Property::class);

        $listings = Property::query()
            ->where('submitted_by', $request->user()->id)
            ->when($request->query('approved') === '1', fn ($query) => $query->where('status', PropertyStatus::Published))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('broker.listings.index', [
            'listings' => $listings,
            'approvedOnly' => $request->query('approved') === '1',
        ]);
    }

    public function create(): View
    {
        $this->authorize('submit', Property::class);

        return view('broker.listings.create', [
            'localities' => Locality::query()->with('county:id,name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, PropertySubmission $submissions): RedirectResponse
    {
        $this->authorize('submit', Property::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'summary' => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'locality_id' => ['required', 'exists:localities,id'],
            'acreage' => ['required', 'numeric', 'gt:0'],
            'sale_mode' => ['required', Rule::enum(SaleMode::class)],
            'offered_acreage' => ['nullable', 'numeric', 'gt:0'],
            'minimum_acreage' => ['nullable', 'numeric', 'gt:0'],
            'seller_sale_instructions' => ['nullable', 'string', 'max:1000'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'purpose' => ['required', Rule::enum(ListingPurpose::class)],
            'property_type' => ['required', Rule::enum(PropertyType::class)],
            'land_use' => ['nullable', Rule::enum(LandUse::class)],
            'parcel_identifier' => ['nullable', 'string', 'max:80'],
            'marketing_role' => ['required', Rule::enum(MarketingRole::class)],
            'seller_name' => ['required', 'string', 'max:160'],
            'seller_contact' => ['required', 'string', 'max:160'],
            'photos' => ['required', 'array', 'min:1', 'max:8'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'authority' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $property = $submissions->submit(
            $request->user(),
            $data,
            $request->file('photos', []),
            $request->file('authority'),
        );

        return redirect()
            ->route('broker.listings.show', $property)
            ->with('status', 'Submission received. It stays unpublished until staff approve it.');
    }

    public function show(Request $request, Property $property, DuplicateListingDetector $duplicates): View
    {
        abort_unless($property->submitted_by === $request->user()->id, 404);

        $property->load(['media', 'informationRequests.staff', 'audits', 'areaCounty', 'areaLocality']);

        return view('broker.listings.show', [
            'listing' => $property,
            'warnings' => $duplicates->warnings(
                $property->parcel_identifier,
                $property->locality_id,
                $property->acreage,
                $property->id,
            ),
        ]);
    }

    public function respond(Request $request, Property $property, PropertyInformationRequest $informationRequest, PropertySubmission $submissions): RedirectResponse
    {
        abort_unless($property->submitted_by === $request->user()->id, 404);
        abort_unless($informationRequest->property_id === $property->id && $informationRequest->isOpen(), 404);

        $data = $request->validate([
            'response' => ['required', 'string', 'max:2000'],
        ]);

        $submissions->respond($request->user(), $informationRequest, $data['response']);

        return back()->with('status', 'Your reply was sent to staff.');
    }

    public function media(Request $request, Property $property, PropertyMedia $propertyMedia): Response
    {
        abort_unless($property->submitted_by === $request->user()->id, 404);
        abort_unless($propertyMedia->property_id === $property->id, 404);
        abort_unless(str_starts_with($propertyMedia->path, 'broker-listings/'.$property->id.'/'), 404);

        $disk = $propertyMedia->disk ?: 'local';

        if ($disk !== 'local') {
            return redirect()->away(Storage::disk($disk)->temporaryUrl($propertyMedia->path, now()->addMinutes(15)));
        }

        return Storage::disk($disk)->download($propertyMedia->path, $this->downloadName($propertyMedia->original_name));
    }

    private function downloadName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));

        return $name === '' ? 'file' : $name;
    }
}
