<?php

namespace App\Http\Controllers\Seller;

use App\Enums\AuthorityScope;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\OwnershipDocumentType;
use App\Enums\PropertyStatus;
use App\Enums\SaleInstructionKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\SellerListingRequest;
use App\Http\Requests\Staff\StorePropertyPhotoRequest;
use App\Models\County;
use App\Models\OwnershipDocument;
use App\Models\Property;
use App\Models\PropertyInformationRequest;
use App\Models\PropertyMedia;
use App\Services\Media\PropertyMediaLibrary;
use App\Services\Seller\SellerPortal;
use App\Services\Verification\VerificationDesk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PropertyController extends Controller
{
    private const MAX_PHOTOS = 12;

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('workspace.seller'), 403);
        $userId = $request->user()->id;

        $properties = Property::query()
            ->where(fn ($query) => $query
                ->where('submitted_by', $userId)
                ->orWhereHas('owners', fn ($owners) => $owners
                    ->where('user_id', $userId)
                    ->where('status', OwnerLinkStatus::Verified)
                    ->whereIn('relationship', OwnerRelationship::sellerFacing())))
            ->withExists(['informationRequests as has_open_request' => fn ($query) => $query->whereNull('responded_at')])
            ->latest()
            ->get();

        return view('seller.properties.index', ['groups' => $this->groups($properties)]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->hasPermission('workspace.seller'), 403);

        return view('seller.properties.create', [
            'property' => new Property(['purpose' => $request->query('purpose') === 'lease' ? 'lease' : 'sale']),
            'relationship' => OwnerRelationship::Owner->value,
            'counties' => $this->counties(),
        ]);
    }

    public function store(SellerListingRequest $request, SellerPortal $portal): RedirectResponse
    {
        $property = $portal->submit($request->user(), $request->validated());

        return redirect()
            ->route('seller.properties.show', $property)
            ->with('status', 'Draft saved. Add photographs and documents, then submit it for review.');
    }

    public function show(Request $request, Property $property, SellerPortal $portal, VerificationDesk $verification): View
    {
        $this->authorizeView($request, $property, $portal);
        $property->load([
            'owners.user',
            'authorities',
            'saleInstructions',
            'passport',
            'verificationItems',
            'amenityStates',
            'areaCounty',
            'areaLocality.parent',
            'informationRequests' => fn ($query) => $query->latest(),
            'ownershipDocuments' => fn ($query) => $query->latest(),
            'media' => fn ($query) => $query->where('kind', 'photo')->orderBy('position'),
        ]);
        $isSubmitter = $portal->isSubmitter($request->user(), $property);

        return view('seller.properties.show', [
            'property' => $property,
            'verified' => $portal->visibleToSeller($request->user(), $property),
            'isSubmitter' => $isSubmitter,
            'canEdit' => $isSubmitter && $property->isEditableBySubmitter(),
            'passport' => $verification->publicSummary($property),
            'requirements' => $portal->documentRequirements($property),
            'gaps' => $property->status === PropertyStatus::Draft ? $portal->submissionGaps($property) : [],
            'photos' => $property->media
                ->filter(fn (PropertyMedia $media): bool => PropertyMediaLibrary::isPublicDerivative($property, $media))
                ->values(),
        ]);
    }

    public function edit(Request $request, Property $property, SellerPortal $portal): View
    {
        $this->authorizeEdit($request, $property, $portal);
        $property->load('amenityStates');

        return view('seller.properties.edit', [
            'property' => $property,
            'relationship' => $property->owners()->where('user_id', $request->user()->id)->value('relationship') ?? OwnerRelationship::Owner->value,
            'counties' => $this->counties(),
        ]);
    }

    public function update(SellerListingRequest $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        $this->authorizeEdit($request, $property, $portal);
        $portal->update($request->user(), $property, $request->validated());

        return redirect()
            ->route('seller.properties.show', $property)
            ->with('status', 'Listing details saved.');
    }

    public function submit(Request $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        abort_unless($portal->isSubmitter($request->user(), $property), 403);
        $portal->submitForReview($request->user(), $property);

        return redirect()
            ->route('seller.properties.show', $property)
            ->with('status', 'Listing submitted. Staff will verify ownership and authority before anything is published.');
    }

    public function document(Request $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        $this->authorizeView($request, $property, $portal);
        abort_if(in_array($property->status, [PropertyStatus::Sold, PropertyStatus::Archived], true), 403);

        $data = $request->validate([
            'document_type' => ['required', Rule::enum(OwnershipDocumentType::class)],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $portal->storeDocument($property, $request->user(), $data['document'], OwnershipDocumentType::from($data['document_type']));

        return back()->with('status', 'Document stored privately. Staff will review it.');
    }

    public function downloadDocument(Request $request, OwnershipDocument $ownershipDocument, SellerPortal $portal): Response
    {
        $property = $ownershipDocument->property;
        $allowed = $request->user()->hasPermission('staff.verification')
            || ($ownershipDocument->user_id === $request->user()->id && ($portal->visibleToSeller($request->user(), $property) || (int) $property->submitted_by === (int) $request->user()->id));

        abort_unless($allowed, 403);
        abort_unless($ownershipDocument->hasFile(), 404);

        if ($ownershipDocument->disk !== 'local') {
            return redirect()->away(Storage::disk($ownershipDocument->disk)->temporaryUrl($ownershipDocument->path, now()->addMinutes(15)));
        }

        return Storage::disk($ownershipDocument->disk)->download($ownershipDocument->path, $ownershipDocument->original_name);
    }

    public function authority(Request $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        abort_unless($portal->visibleToSeller($request->user(), $property), 403);

        $data = $request->validate([
            'terms' => ['required', 'string', 'min:20', 'max:5000'],
            'scope' => ['required', Rule::enum(AuthorityScope::class)],
            'valid_until' => ['nullable', 'date', 'after:today'],
        ]);

        $portal->submitAuthority($property, $request->user(), $data['terms'], AuthorityScope::from($data['scope']), $data['valid_until'] ?? null);

        return back()->with('status', 'Marketing authority submitted for staff review.');
    }

    public function instruction(Request $request, Property $property, SellerPortal $portal): RedirectResponse
    {
        abort_unless($portal->visibleToSeller($request->user(), $property), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(SaleInstructionKind::class)->except([SaleInstructionKind::OfferResponse])],
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

    public function respond(Request $request, Property $property, PropertyInformationRequest $informationRequest, SellerPortal $portal): RedirectResponse
    {
        abort_unless($portal->isSubmitter($request->user(), $property), 403);
        abort_unless($informationRequest->property_id === $property->id, 404);

        $data = $request->validate(['response' => ['required', 'string', 'max:2000']]);
        $portal->respondToRequest($request->user(), $informationRequest, $data['response']);

        return back()->with('status', 'Reply sent to staff.');
    }

    public function storePhoto(StorePropertyPhotoRequest $request, Property $property, PropertyMediaLibrary $library): RedirectResponse
    {
        if ($property->media()->where('kind', 'photo')->count() >= self::MAX_PHOTOS) {
            throw ValidationException::withMessages(['photo' => 'A listing can hold up to '.self::MAX_PHOTOS.' photographs.']);
        }

        $library->storePhoto($request->user(), $property, $request->file('photo'));

        return back()->with('status', 'Photo stored. The public copy has had location data removed.');
    }

    public function destroyPhoto(Property $property, PropertyMedia $propertyMedia, PropertyMediaLibrary $library): RedirectResponse
    {
        abort_unless($propertyMedia->property_id === $property->id && $propertyMedia->kind === 'photo', 404);
        $this->authorize('delete', $propertyMedia);
        $library->delete($propertyMedia);

        return back()->with('status', 'Photo removed.');
    }

    public function coverPhoto(Property $property, PropertyMedia $propertyMedia, PropertyMediaLibrary $library): RedirectResponse
    {
        $this->authorize('manageMedia', $property);
        abort_unless($propertyMedia->property_id === $property->id, 404);
        $library->makeCover($property, $propertyMedia);

        return back()->with('status', 'Cover photo updated.');
    }

    public function reorderPhotos(Request $request, Property $property, PropertyMediaLibrary $library): RedirectResponse
    {
        $this->authorize('manageMedia', $property);
        $data = $request->validate([
            'positions' => ['required', 'array'],
            'positions.*' => ['integer', 'min:1', 'max:1000'],
        ]);
        $library->reorder($property, $data['positions']);

        return back()->with('status', 'Photo order saved.');
    }

    public function previewPhoto(Request $request, Property $property, PropertyMedia $propertyMedia, SellerPortal $portal): StreamedResponse
    {
        $this->authorizeView($request, $property, $portal);
        abort_unless($propertyMedia->property_id === $property->id, 404);
        abort_unless(PropertyMediaLibrary::isPublicDerivative($property, $propertyMedia), 404);

        $disk = $propertyMedia->public_disk ?: 'local';

        return Storage::disk($disk)->response($propertyMedia->public_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * @param  Collection<int, Property>  $properties
     * @return array<string, array{title: string, empty: string, items: Collection<int, Property>}>
     */
    private function groups(Collection $properties): array
    {
        $open = fn (Property $property): bool => (bool) $property->has_open_request
            && in_array($property->status, [PropertyStatus::Draft, PropertyStatus::Review], true);

        return [
            'returned' => [
                'title' => 'Returned for corrections',
                'empty' => 'Nothing is waiting on you.',
                'items' => $properties->filter($open)->values(),
            ],
            'drafts' => [
                'title' => 'Drafts',
                'empty' => 'No drafts.',
                'items' => $properties->filter(fn (Property $property) => $property->status === PropertyStatus::Draft && ! $open($property))->values(),
            ],
            'review' => [
                'title' => 'Awaiting ownership verification',
                'empty' => 'No submissions are with staff.',
                'items' => $properties->filter(fn (Property $property) => $property->status === PropertyStatus::Review && ! $open($property))->values(),
            ],
            'published' => [
                'title' => 'Published listings',
                'empty' => 'No published listings yet.',
                'items' => $properties->filter(fn (Property $property) => in_array($property->status, [PropertyStatus::Published, PropertyStatus::Reserved], true))->values(),
            ],
            'closed' => [
                'title' => 'Unavailable, completed or withdrawn',
                'empty' => 'None.',
                'items' => $properties->filter(fn (Property $property) => in_array($property->status, [
                    PropertyStatus::Sold,
                    PropertyStatus::Leased,
                    PropertyStatus::Withdrawn,
                    PropertyStatus::Suspended,
                    PropertyStatus::Archived,
                ], true))->values(),
            ],
        ];
    }

    private function counties(): Collection
    {
        return County::query()
            ->active()
            ->with(['localities' => fn ($query) => $query->active()->with('parent:id,name')->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    private function authorizeView(Request $request, Property $property, SellerPortal $portal): void
    {
        abort_unless($portal->visibleToSeller($request->user(), $property) || $portal->isSubmitter($request->user(), $property), 403);
    }

    private function authorizeEdit(Request $request, Property $property, SellerPortal $portal): void
    {
        abort_unless($portal->isSubmitter($request->user(), $property) && $property->isEditableBySubmitter(), 403);
    }
}
