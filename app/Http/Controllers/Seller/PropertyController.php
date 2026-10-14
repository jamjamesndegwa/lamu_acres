<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\SaleInstructionKind;
use App\Http\Controllers\Controller;
use App\Models\OwnershipDocument;
use App\Models\Property;
use App\Services\Seller\SellerPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyController extends Controller
{
    public function index(Request $request, SellerPortal $portal): View
    {
        abort_unless($request->user()->hasPermission('workspace.seller'), 403);

        $properties = Property::query()
            ->whereHas('owners', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('status', OwnerLinkStatus::Verified)
                ->whereIn('relationship', [OwnerRelationship::Owner, OwnerRelationship::Authorized]))
            ->latest()
            ->paginate(12);

        return view('seller.properties.index', ['properties' => $properties]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->hasPermission('workspace.seller'), 403);

        $submissions = Property::query()
            ->where('submitted_by', $request->user()->id)
            ->latest()
            ->get();

        return view('seller.properties.create', ['submissions' => $submissions]);
    }

    public function store(Request $request, SellerPortal $portal): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('workspace.seller'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'county' => ['nullable', 'string', 'max:80'],
            'locality' => ['nullable', 'string', 'max:80'],
            'acreage' => ['nullable', 'numeric', 'min:0'],
            'summary' => ['nullable', 'string', 'max:2000'],
        ]);

        $property = $portal->submit($request->user(), $data);

        return redirect()
            ->route('seller.properties.show', $property)
            ->with('status', 'Property submitted for an ownership check.');
    }

    public function show(Request $request, Property $property, SellerPortal $portal): View
    {
        $this->authorizeSeller($request, $property, $portal, allowOwnDraft: true);
        $property->load(['owners.user', 'authorities', 'saleInstructions', 'passport', 'verificationItems']);

        return view('seller.properties.show', [
            'property' => $property,
            'verified' => $portal->visibleToSeller($request->user(), $property),
            'passport' => app(\App\Services\Verification\VerificationDesk::class)->publicSummary($property),
        ]);
    }

    public function document(Request $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        $this->authorizeSeller($request, $property, $portal, allowOwnDraft: true);

        $data = $request->validate([
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $portal->storeDocument($property, $request->user(), $data['document']);

        return back()->with('status', 'Ownership document stored privately.');
    }

    public function downloadDocument(Request $request, OwnershipDocument $ownershipDocument, SellerPortal $portal): StreamedResponse
    {
        $property = $ownershipDocument->property;
        $allowed = $request->user()->hasPermission('staff.verification')
            || ($ownershipDocument->user_id === $request->user()->id && ($portal->visibleToSeller($request->user(), $property) || (int) $property->submitted_by === (int) $request->user()->id));

        abort_unless($allowed, 403);

        return Storage::disk($ownershipDocument->disk)->download($ownershipDocument->path, $ownershipDocument->original_name);
    }

    public function authority(Request $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        $this->authorizeSeller($request, $property, $portal);

        $data = $request->validate([
            'terms' => ['required', 'string', 'min:20', 'max:5000'],
        ]);

        $portal->submitAuthority($property, $request->user(), $data['terms']);

        return back()->with('status', 'Marketing authority submitted for staff review.');
    }

    public function instruction(Request $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        $this->authorizeSeller($request, $property, $portal);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(SaleInstructionKind::class)],
            'body' => ['required', 'string', 'min:5', 'max:2000'],
            'requested_availability' => ['nullable', Rule::in(['available', 'unavailable'])],
        ]);

        $portal->instruct(
            $property,
            $request->user(),
            SaleInstructionKind::from($data['kind']),
            $data['body'],
            $data['requested_availability'] ?? null,
        );

        return back()->with('status', 'Instruction recorded.');
    }

    private function authorizeSeller(Request $request, Property $property, SellerPortal $portal, bool $allowOwnDraft = false): void
    {
        $ownDraft = $allowOwnDraft
            && (int) $property->submitted_by === (int) $request->user()->id
            && $property->status === \App\Enums\PropertyStatus::Draft;

        abort_unless($portal->visibleToSeller($request->user(), $property) || $ownDraft, 403);
    }
}
