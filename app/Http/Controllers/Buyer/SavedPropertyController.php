<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Services\Buyer\SavedPropertyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SavedPropertyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Models\SavedProperty::class);

        $saved = $request->user()
            ->savedProperties()
            ->with(['property.areaCounty:id,name,slug', 'property.areaLocality:id,name,slug,county_id'])
            ->latest()
            ->paginate(12);

        return view('buyer.saved.index', ['saved' => $saved]);
    }

    public function destroy(Request $request, Property $property, SavedPropertyService $saved): RedirectResponse
    {
        $this->authorize('viewAny', \App\Models\SavedProperty::class);

        $saved->remove($request->user(), $property);

        return back()->with('status', 'Property removed from saved land.');
    }
}
