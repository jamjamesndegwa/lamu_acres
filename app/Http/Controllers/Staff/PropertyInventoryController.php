<?php

namespace App\Http\Controllers\Staff;

use App\Enums\Amenity;
use App\Enums\DocumentCheckState;
use App\Enums\OwnerRelationship;
use App\Enums\PropertyStatus;
use App\Enums\RoadAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\PropertyInventoryRequest;
use App\Http\Requests\Staff\StaffPropertyListingRequest;
use App\Models\County;
use App\Models\InfrastructureFeature;
use App\Models\Property;
use App\Models\User;
use App\Services\Inventory\PropertyInventory;
use App\Services\Media\PropertyMediaLibrary;
use App\Services\Seller\SellerPortal;
use App\Services\Verification\VerificationOperations;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PropertyInventoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('create', Property::class);

        $properties = Property::query()
            ->with(['areaCounty:id,name,slug', 'areaLocality:id,name,slug'])
            ->withSum(['portions as sold_portion_acreage' => fn ($query) => $query->where('status', PropertyStatus::Sold)], 'acreage')
            ->withCount('offers')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $like = '%'.addcslashes((string) $request->query('q'), '%_\\').'%';
                $query->where(fn ($inner) => $inner->where('title', 'like', $like)->orWhere('reference', 'like', $like));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('county_id'), fn ($query) => $query->where('county_id', $request->integer('county_id')))
            ->when($request->filled('purpose'), fn ($query) => $query->where('purpose', $request->query('purpose')))
            ->whereNull('parent_id')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('staff.properties.index', [
            'properties' => $properties,
            'counties' => County::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Property::class);

        if ($request->filled('parent')) {
            return view('staff.properties.form', [
                'property' => new Property,
                'parent' => Property::query()->find($request->integer('parent')),
                'counties' => County::query()->active()->with(['localities' => fn ($query) => $query->active()->orderBy('name')])->orderBy('name')->get(),
                'modal' => Turbo::wantsModal($request),
            ]);
        }

        return view('staff.properties.create', [
            'property' => new Property(['purpose' => $request->query('purpose') === 'lease' ? 'lease' : 'sale']),
            'relationship' => OwnerRelationship::Owner->value,
            'counties' => County::query()->active()->with(['localities' => fn ($query) => $query->active()->orderBy('name')])->orderBy('name')->get(),
            'owners' => User::role('seller')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request, PropertyInventory $inventory, SellerPortal $portal, PropertyMediaLibrary $library): RedirectResponse
    {
        $this->authorize('create', Property::class);

        if ($request->filled('parent_id')) {
            $validated = $request->validate((new PropertyInventoryRequest)->rules());
            $inventory->create($request->user(), $validated);

            return redirect()->route('staff.properties.index')->with('status', 'Property portion saved as a draft.');
        }

        $listing = StaffPropertyListingRequest::createFrom($request);
        $listing->setContainer(app())->setRedirector(app('redirect'));
        $listing->validateResolved();

        $property = $portal->registerByStaff($request->user(), $listing->validated());

        foreach ($request->file('photos', []) ?? [] as $photo) {
            $library->storePhoto($request->user(), $property, $photo);
        }

        $property = $portal->finalizeStaffRegistration($request->user(), $property->fresh(['media', 'owners', 'authorities']));

        return redirect()
            ->route('staff.properties.show', $property)
            ->with('status', 'Property registered and published to the land directory.');
    }

    public function edit(Request $request, Property $property): View
    {
        $this->authorize('update', $property);

        return view('staff.properties.form', [
            'property' => $property->load('portions'),
            'parent' => $property->parent,
            'counties' => County::query()->active()->with(['localities' => fn ($query) => $query->active()->orderBy('name')])->orderBy('name')->get(),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function update(PropertyInventoryRequest $request, Property $property, PropertyInventory $inventory): RedirectResponse
    {
        $this->authorize('update', $property);
        $inventory->update($request->user(), $property, $request->validated());

        return redirect()->route('staff.properties.show', $property)->with('status', 'Property updated.');
    }

    public function show(Request $request, Property $property): View
    {
        $this->authorize('update', $property);
        $property->load([
            'areaCounty:id,name,slug',
            'areaLocality:id,name,slug',
            'parent:id,slug,reference,title,acreage',
            'portions',
            'submitter:id,name,email',
            'representative:id,name',
            'owners.user:id,name,email',
            'authorities.seller:id,name',
            'enquiries.buyer:id,name',
            'offers.buyer:id,name,email,phone',
            'transactions.confirmer:id,name',
            'salesTransactions',
            'saleInstructions.seller:id,name',
            'ownershipDocuments' => fn ($query) => $query->with('reviewer:id,name')->latest(),
            'audits.actor:id,name',
            'priceChanges',
            'availabilityEvents',
            'documentChecks',
            'highlights',
            'amenityStates',
            'passport',
            'verificationItems.reviewer:id,name',
            'media',
        ]);
        $property->loadSum(['portions as sold_portion_acreage' => fn ($query) => $query->where('status', PropertyStatus::Sold)], 'acreage');

        if (Turbo::wantsModal($request)) {
            $dialog = $request->query('dialog') === 'portion' ? 'portion' : 'status';

            return view('staff.properties.dialogs.'.$dialog, ['property' => $property]);
        }

        $tab = (string) $request->query('tab', 'overview');

        if (! in_array($tab, ['overview', 'owners', 'verification', 'marketing', 'enquiries', 'offers', 'transactions', 'history'], true)) {
            $tab = 'overview';
        }

        $requirements = app(SellerPortal::class)->documentRequirements($property);

        return view('staff.properties.show', [
            'property' => $property,
            'tab' => $tab,
            'amenities' => Amenity::cases(),
            'roads' => RoadAccess::cases(),
            'documentStates' => DocumentCheckState::cases(),
            'blockers' => app(VerificationOperations::class)->blockers($property),
            'requirements' => $requirements,
            'approvalSteps' => app(VerificationOperations::class)->approvalSteps($property, $requirements),
            'infrastructure' => $property->county_id
                ? InfrastructureFeature::query()->active()->where('county_id', $property->county_id)->orderBy('name')->get()
                : collect(),
        ]);
    }

    public function transition(Request $request, Property $property, PropertyInventory $inventory): RedirectResponse
    {
        $this->authorize('update', $property);

        $data = $request->validate([
            'status' => ['required', Rule::enum(PropertyStatus::class)],
        ]);

        $inventory->transition($request->user(), $property, PropertyStatus::from($data['status']));

        return back()->with('status', 'Property status updated.');
    }

    public function sellPortion(Request $request, Property $property, PropertyInventory $inventory): RedirectResponse
    {
        $this->authorize('update', $property);
        $data = $request->validate([
            'correction_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $inventory->sellPortion($request->user(), $property, $data['correction_reason'] ?? null);

        return back()->with('status', 'Portion marked sold. The parent parcel stays open while acreage remains.');
    }
}
