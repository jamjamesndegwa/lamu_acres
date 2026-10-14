<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Staff\Concerns\AuthorizesIntelligence;
use App\Models\VerifiedSale;
use App\Services\Intelligence\IntelligenceFiles;
use App\Services\Intelligence\PriceObservatory;
use App\Services\Intelligence\PublicationDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerifiedSaleController extends Controller
{
    use AuthorizesIntelligence;

    public function index(PriceObservatory $observatory): View
    {
        return view('staff.intelligence.prices.index', [
            'rows' => $observatory->askingByLocality(),
            'sales' => VerifiedSale::query()->with('locality')->latest()->paginate(20),
            'localities' => $this->localities(),
        ]);
    }

    public function store(Request $request, PublicationDesk $desk, IntelligenceFiles $files): RedirectResponse
    {
        $this->researcher($request);
        $data = $request->validate([
            'locality_id' => ['nullable', 'exists:localities,id'],
            'acreage' => ['required', 'numeric', 'gt:0'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'sold_on' => ['required', 'date'],
            'source_name' => ['required', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $sale = VerifiedSale::query()->create([
            'reference' => $desk->reference('VS', VerifiedSale::class),
            'locality_id' => $data['locality_id'] ?? null,
            'acreage' => $data['acreage'],
            'price_amount' => $data['price_amount'],
            'price_currency' => 'KES',
            'sold_on' => $data['sold_on'],
            'source_name' => $data['source_name'],
            'note' => $data['note'] ?? null,
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $request->user()->id,
        ]);
        $files->store($sale, $request->user(), $request->file('evidence'), 'evidence');
        $desk->audit($sale, $request->user(), 'created', null, PublicationStatus::Draft, 'Recorded as a verified sale, separate from asking prices.');

        return back()->with('status', 'Verified sale saved as a draft. It is not included in the asking-price median.');
    }

    public function submit(Request $request, VerifiedSale $sale, PublicationDesk $desk): RedirectResponse
    {
        $this->researcher($request);
        $desk->submit($sale, $request->user());

        return back()->with('status', 'Verified sale submitted for review.');
    }

    public function publish(Request $request, VerifiedSale $sale, PublicationDesk $desk): RedirectResponse
    {
        $this->editor($request);
        $desk->publish($sale, $request->user());

        return back()->with('status', 'Verified sale published. It remains separate from asking prices.');
    }
}
