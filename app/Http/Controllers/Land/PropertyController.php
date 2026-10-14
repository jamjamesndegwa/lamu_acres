<?php

namespace App\Http\Controllers\Land;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\StoreEnquiryRequest;
use App\Models\Property;
use App\Models\SavedProperty;
use App\Services\Buyer\EnquiryRecorder;
use App\Services\Buyer\SavedPropertyService;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PropertyController extends Controller
{
    public function index(): View
    {
        return view('land.index', [
            'properties' => Property::query()->published()->orderByDesc('published_at')->paginate(12),
        ]);
    }

    public function show(Property $property): View
    {
        abort_unless($property->isPublished(), 404);

        $saved = false;

        if ($user = auth()->user()) {
            $saved = SavedProperty::query()
                ->where('user_id', $user->id)
                ->where('property_id', $property->id)
                ->exists();
        }

        return view('land.show', [
            'property' => $property,
            'saved' => $saved,
        ]);
    }

    public function createEnquiry(Property $property): View|RedirectResponse
    {
        abort_unless($property->isPublished(), 404);
        $this->authorize('enquire', $property);

        if (! Turbo::wantsModal(request())) {
            return redirect()->to($property->publicUrl());
        }

        return view('land.enquire', ['property' => $property]);
    }

    public function storeEnquiry(StoreEnquiryRequest $request, Property $property, EnquiryRecorder $recorder): RedirectResponse
    {
        $recorder->submit($request->user(), $property, $request->string('message')->toString(), $request);

        return redirect()
            ->route('buyer.enquiries.index')
            ->with('status', 'Your enquiry has been sent.');
    }

    public function save(Request $request, Property $property, SavedPropertyService $saved): RedirectResponse
    {
        abort_unless($property->isPublished(), 404);
        $this->authorize('save', $property);

        $saved->save($request->user(), $property);

        return back()->with('status', 'Property saved.');
    }

    public function unsave(Request $request, Property $property, SavedPropertyService $saved): RedirectResponse
    {
        $this->authorize('save', $property);

        $saved->remove($request->user(), $property);

        return back()->with('status', 'Property removed from saved land.');
    }
}
