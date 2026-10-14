<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PropertyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\PropertyInventoryRequest;
use App\Models\County;
use App\Models\Property;
use App\Services\Inventory\PropertyInventory;
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
            ->with(['areaCounty:id,name', 'areaLocality:id,name'])
            ->withSum(['portions as sold_portion_acreage' => fn ($query) => $query->where('status', PropertyStatus::Sold)], 'acreage')
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

        return view('staff.properties.form', [
            'property' => new Property,
            'parent' => $request->filled('parent') ? Property::query()->find($request->integer('parent')) : null,
            'counties' => County::query()->active()->with(['localities' => fn ($query) => $query->active()->orderBy('name')])->orderBy('name')->get(),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function store(PropertyInventoryRequest $request, PropertyInventory $inventory): RedirectResponse
    {
        $inventory->create($request->user(), $request->validated());

        return redirect()->route('staff.properties.index')->with('status', 'Property saved as a draft.');
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

        return redirect()->route('staff.properties.index')->with('status', 'Property updated.');
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
        $inventory->sellPortion($request->user(), $property);

        return back()->with('status', 'Portion marked sold. The parent parcel stays open while acreage remains.');
    }
}
