<?php

namespace App\Http\Controllers\Staff;

use App\Enums\AuthorityStatus;
use App\Enums\MessageEvent;
use App\Enums\OwnerRelationship;
use App\Enums\OwnershipDocumentType;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\TransactionStatus;
use App\Enums\VerificationItemState;
use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\MarketingAuthority;
use App\Models\Offer;
use App\Models\OwnershipDocument;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\PropertyOwner;
use App\Models\PropertyTransaction;
use App\Models\User;
use App\Services\Communications\MessageDesk;
use App\Services\Inventory\PropertyInventory;
use App\Services\Seller\SellerPortal;
use App\Support\ApprovalsReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SellerDeskController extends Controller
{
    public function verifyOwner(Request $request, PropertyOwner $propertyOwner, SellerPortal $portal): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.verification') || $request->user()->hasPermission('staff.properties'), 403);

        $data = $request->validate(['share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'], 'identity_consistency_confirmed' => ['accepted'], 'reason' => ['required', 'string', 'min:8', 'max:1000']]);
        $portal->verifyLink($propertyOwner, $request->user(), isset($data['share_percent']) ? (float) $data['share_percent'] : null, $data['reason'], true);

        return back()->with('status', 'Ownership link verified.');
    }

    public function addOwner(Request $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.properties'), 403);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'relationship' => ['required', Rule::enum(OwnerRelationship::class)],
        ]);
        $seller = User::query()->findOrFail($data['user_id']);
        abort_unless($seller->hasPermission('workspace.seller'), 422);

        $portal->addOwner($property, $seller, OwnerRelationship::from($data['relationship']), $request->user());

        return back()->with('status', 'Owner linked to the existing property.');
    }

    public function authorities(Request $request): View
    {
        abort_unless($request->user()->hasPermission('staff.brokerage'), 403);

        return view('staff.brokerage.authorities', [
            'authorities' => MarketingAuthority::query()->with(['property', 'seller'])->latest()->paginate(20),
        ]);
    }

    public function decideAuthority(Request $request, MarketingAuthority $marketingAuthority, SellerPortal $portal): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.properties'), 403);

        $data = $request->validate(['status' => ['required', Rule::in(['active', 'declined', 'withdrawn'])]]);
        $portal->decideAuthority($marketingAuthority, AuthorityStatus::from($data['status']), $request->user());

        return ApprovalsReturn::back($request, 'authorities', 'Marketing authority updated.');
    }

    public function approveListing(Request $request, Property $property, PropertyInventory $inventory): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.properties'), 403);

        $from = $property->status;
        $inventory->publish($request->user(), $property);

        PropertyAudit::query()->create([
            'property_id' => $property->id,
            'actor_id' => $request->user()->id,
            'action' => 'approved',
            'from_status' => $from->value,
            'to_status' => PropertyStatus::Published->value,
        ]);



        return back()->with('status', 'Listing approved.');
    }

    public function reviewDocument(Request $request, OwnershipDocument $ownershipDocument, SellerPortal $portal): RedirectResponse
    {
        $data = $request->validate([
            'state' => ['required', Rule::in([
                VerificationItemState::UnderReview->value,
                VerificationItemState::Verified->value,
                VerificationItemState::Failed->value,
                VerificationItemState::Expired->value,
                VerificationItemState::NotApplicable->value,
            ])],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $portal->reviewDocument($ownershipDocument, $request->user(), VerificationItemState::from($data['state']), $data['reason'], $data['expires_at'] ?? null);

        return back()->with('status', 'Document review recorded.');
    }

    public function approveDocuments(Request $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        $data = $request->validate([
            'document_ids' => ['required', 'array', 'min:1', 'max:100'],
            'document_ids.*' => ['required', 'integer', 'distinct'],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $property, $portal, $data): void {
            $documents = $property->ownershipDocuments()
                ->whereIn('id', $data['document_ids'])
                ->lockForUpdate()
                ->get();

            abort_unless($documents->count() === count($data['document_ids']), 404);
            abort_if($documents->contains(fn (OwnershipDocument $document): bool => ! $document->hasFile()), 422, 'Only uploaded documents can be approved.');

            foreach ($documents as $document) {
                $portal->reviewDocument($document, $request->user(), VerificationItemState::Verified, $data['reason'], $document->expires_at?->format('Y-m-d'));
            }
        });

        return back()->with('status', count($data['document_ids']).' documents approved.');
    }

    public function documentNotApplicable(Request $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        $data = $request->validate([
            'document_type' => ['required', Rule::enum(OwnershipDocumentType::class)],
            'reason' => ['required', 'string', 'min:8', 'max:2000'],
        ]);

        $portal->markDocumentNotApplicable($property, OwnershipDocumentType::from($data['document_type']), $request->user(), $data['reason']);

        return back()->with('status', 'Document recorded as not applicable.');
    }

    public function downloadDocument(Request $request, OwnershipDocument $ownershipDocument): Response
    {
        abort_unless($ownershipDocument->hasFile(), 404);

        PropertyAudit::query()->create([
            'property_id' => $ownershipDocument->property_id,
            'actor_id' => $request->user()->id,
            'action' => 'ownership_document_opened',
            'changes' => ['document' => $ownershipDocument->id, 'type' => $ownershipDocument->document_type?->value],
        ]);

        if ($request->boolean('inline')) {
            $mime = Storage::disk($ownershipDocument->disk)->mimeType($ownershipDocument->path);
            abort_unless(in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true), 415);

            return Storage::disk($ownershipDocument->disk)->response($ownershipDocument->path, $ownershipDocument->original_name, [
                'Content-Type' => $mime,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        if ($ownershipDocument->disk !== 'local') {
            return redirect()->away(Storage::disk($ownershipDocument->disk)->temporaryUrl($ownershipDocument->path, now()->addMinutes(15)));
        }

        return Storage::disk($ownershipDocument->disk)->download($ownershipDocument->path, $ownershipDocument->original_name);
    }

    public function recordLease(Request $request, Property $property, PropertyInventory $inventory): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.properties') || $request->user()->hasPermission('staff.sales'), 403);

        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'acreage' => ['required', 'numeric', 'gt:0'],
            'amount' => ['required', 'integer', 'min:1'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'statement' => ['nullable', 'string', 'max:2000'],
        ]);

        $inventory->recordLease($request->user(), $property, $data);

        return back()->with('status', 'Lease recorded. The land is no longer advertised as available.');
    }

    public function endLease(Request $request, PropertyTransaction $propertyTransaction, PropertyInventory $inventory): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.properties') || $request->user()->hasPermission('staff.sales'), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'min:8', 'max:2000']]);
        $inventory->endLease($request->user(), $propertyTransaction, $data['reason']);

        return back()->with('status', 'Lease ended. The land is back in review before it is advertised again.');
    }

    public function applyAvailability(Request $request, Property $property): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.properties'), 403);

        $data = $request->validate(['availability' => ['required', Rule::in(['available', 'unavailable'])]]);
        $property->availability = PropertyAvailability::from($data['availability']);
        $property->save();

        return back()->with('status', 'Availability updated.');
    }

    public function briefing(Request $request, Enquiry $enquiry): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.buyers'), 403);

        $data = $request->validate(['body' => ['required', 'string', 'min:5', 'max:2000']]);
        $enquiry->property->briefings()->create([
            'enquiry_id' => $enquiry->id,
            'shared_by' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back()->with('status', 'Enquiry shared with the seller.');
    }

    public function presentOffer(Request $request, Offer $offer, SellerPortal $portal): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.sales') || $request->user()->hasPermission('staff.buyers'), 403);

        $data = $request->validate(['summary' => ['required', 'string', 'min:5', 'max:2000']]);
        $portal->presentOffer($offer, $request->user(), $data['summary']);

        return back()->with('status', 'Offer presented to the seller.');
    }

    public function transaction(Request $request, Property $property): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('staff.sales'), 403);

        $data = $request->validate([
            'status' => ['required', Rule::enum(TransactionStatus::class)],
            'amount' => ['nullable', 'integer', 'min:0'],
            'statement' => ['nullable', 'string', 'max:5000'],
        ]);

        PropertyTransaction::query()->create([
            'reference' => 'TX-'.strtoupper(Str::random(8)),
            'property_id' => $property->id,
            'status' => $data['status'],
            'amount' => $data['amount'] ?? null,
            'statement' => $data['statement'] ?? null,
        ]);

        return back()->with('status', 'Transaction statement recorded.');
    }
}
