<?php

namespace App\Services\Seller;

use App\Enums\Amenity;
use App\Enums\AmenityState;
use App\Enums\AuthorityScope;
use App\Enums\AuthorityStatus;
use App\Enums\LandTenure;
use App\Enums\LandUse;
use App\Enums\ListingPurpose;
use App\Enums\MarketingRole;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\OwnershipDocumentType;
use App\Enums\OwnershipStructure;
use App\Enums\PriceBasis;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\SaleInstructionKind;
use App\Enums\SubdivisionStatus;
use App\Enums\VerificationItemState;
use App\Http\Requests\Seller\SellerListingRequest;
use App\Models\Locality;
use App\Models\MarketingAuthority;
use App\Models\Offer;
use App\Models\OfferPresentation;
use App\Models\OwnershipDocument;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\PropertyInformationRequest;
use App\Models\PropertyOwner;
use App\Models\SaleInstruction;
use App\Models\User;
use App\Notifications\PropertySubmittedNotification;
use App\Services\Broker\DuplicateListingDetector;
use App\Services\Broker\PropertySubmission;
use App\Services\Inventory\PropertyInventory;
use App\Services\Verification\VerificationDesk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerPortal
{
    public const REQUIRED = 'required';

    public const CONDITIONAL = 'conditional';

    public const STAFF_TO_DETERMINE = 'staff_to_determine';

    public const STAFF_OBTAINS = 'staff_obtains';

    public const AT_COMPLETION = 'at_completion';

    public const OPTIONAL = 'optional';

    public function __construct(
        private VerificationDesk $verification,
        private PropertyInventory $inventory,
        private DuplicateListingDetector $duplicates,
    ) {}

    /**
     * Save a new landowner listing as a draft. Nothing is published or verified here.
     *
     * @param  array<string, mixed>  $data
     */
    public function submit(User $seller, array $data): Property
    {
        return DB::transaction(function () use ($seller, $data): Property {
            $relationship = OwnerRelationship::tryFrom((string) ($data['relationship'] ?? '')) ?? OwnerRelationship::Owner;
            $property = new Property;
            $this->fillListing($property, $data);
            $property->forceFill([
                'reference' => $this->reference('SP'),
                'slug' => Str::slug((string) $data['title']).'-'.Str::lower(Str::random(5)),
                'status' => PropertyStatus::Draft,
                'availability' => PropertyAvailability::Unavailable,
                'submitted_by' => $seller->id,
                'price_currency' => 'KES',
                'marketing_role' => $this->marketingRole($relationship),
            ]);
            $property->save();

            $property->owners()->create([
                'user_id' => $seller->id,
                'relationship' => $relationship,
                'status' => OwnerLinkStatus::Pending,
            ]);

            $this->syncAmenities($property, $data['amenities'] ?? []);
            $this->verification->seed($property);

            return $property;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $seller, Property $property, array $data): Property
    {
        if (! $property->isEditableBySubmitter()) {
            throw ValidationException::withMessages([
                'listing' => 'This listing is with staff. Send changes as an instruction instead.',
            ]);
        }

        return DB::transaction(function () use ($seller, $property, $data): Property {
            $relationship = OwnerRelationship::tryFrom((string) ($data['relationship'] ?? '')) ?? OwnerRelationship::Owner;
            $this->fillListing($property, $data);
            $property->marketing_role = $this->marketingRole($relationship);
            $property->save();

            $property->owners()
                ->where('user_id', $seller->id)
                ->where('status', OwnerLinkStatus::Pending)
                ->update(['relationship' => $relationship->value]);

            $this->syncAmenities($property, $data['amenities'] ?? []);

            return $property;
        });
    }

    /**
     * Send a complete draft to the staff review queue. Publication remains a staff action.
     */
    public function submitForReview(User $seller, Property $property): Property
    {
        if ($property->status !== PropertyStatus::Draft) {
            throw ValidationException::withMessages([
                'listing' => 'Only a draft listing can be submitted for review.',
            ]);
        }

        $gaps = $this->submissionGaps($property);

        if ($gaps !== []) {
            throw ValidationException::withMessages($gaps);
        }

        $this->inventory->transition($seller, $property, PropertyStatus::Review);
        $property->submitted_at = now();
        $property->save();
        $this->verification->seed($property);

        PropertyAudit::query()->create([
            'property_id' => $property->id,
            'actor_id' => $seller->id,
            'action' => 'submitted',
            'from_status' => PropertyStatus::Draft->value,
            'to_status' => PropertyStatus::Review->value,
            'changes' => [
                'duplicate_warnings' => $this->duplicates->warnings(
                    $property->parcel_identifier,
                    $property->locality_id,
                    $property->acreage,
                    $property->id,
                ),
                'staff_to_determine' => collect($this->documentRequirements($property))
                    ->where('requirement', self::STAFF_TO_DETERMINE)
                    ->pluck('label')
                    ->values()
                    ->all(),
            ],
        ]);

        Notification::send(
            User::permission('staff.properties')->get(),
            new PropertySubmittedNotification($property, 'A landowner submitted land for review.'),
        );

        return $property;
    }

    /**
     * Fields and evidence that must be in place before a draft can go to staff review.
     *
     * @return array<string, string>
     */
    public function submissionGaps(Property $property): array
    {
        $gaps = [];
        $required = [
            'title' => 'Add a property title.',
            'summary' => 'Add a short summary.',
            'description' => 'Add a detailed description.',
            'property_type' => 'Choose the property type.',
            'land_use' => 'Choose the land use category.',
            'tenure' => 'Choose the tenure or ownership classification.',
            'ownership_structure' => 'Say how the land is owned.',
            'locality_id' => 'Choose the county and locality.',
            'acreage' => 'Add the registered parcel acreage.',
            'parcel_identifier' => 'Add the parcel, title or other land reference.',
            'parcel_reference_type' => 'Say what kind of land reference you entered.',
        ];

        foreach ($required as $field => $message) {
            if (blank($property->{$field})) {
                $gaps[$field] = $message;
            }
        }

        if ($property->purpose === null) {
            $gaps['purpose'] = 'Choose sale or lease.';
        }

        if ($property->acreage !== null && $property->offered_acreage !== null
            && (float) $property->offered_acreage > $property->remainingAcreage() + 0.0001) {
            $gaps['offered_acreage'] = 'The acreage offered is larger than the land still held.';
        }

        if ($property->isPartialOffer() && $property->subdivision_status === null) {
            $gaps['subdivision_status'] = 'Say whether the portion offered has been subdivided.';
        }

        if ($property->price_amount === null) {
            $gaps[$property->isLease() ? 'lease.amount' : 'sale.amount'] = $property->isLease()
                ? 'Add the asking rent.'
                : 'Add the asking price.';
        } elseif ($property->purpose !== null && $property->priceBasis()->purpose() !== $property->purpose) {
            $gaps[$property->isLease() ? 'lease.price_basis' : 'sale.price_basis'] = 'Choose a price basis that matches the listing purpose.';
        }

        if ($property->isLease() && $property->lease_min_months === null) {
            $gaps['lease.min_months'] = 'Add the minimum lease duration.';
        }

        if (! $property->media()->where('kind', 'photo')->exists()) {
            $gaps['photos'] = 'Upload at least one photograph of the land.';
        }

        foreach ($this->documentRequirements($property) as $requirement) {
            if ($requirement['requirement'] === self::REQUIRED && ! $requirement['supplied']) {
                $gaps['documents.'.$requirement['type']] = 'Upload the '.Str::lower($requirement['label']).'.';
            }
        }

        return $gaps;
    }

    /**
     * Which ownership and legal documents apply to this listing, and why.
     *
     * @return array<int, array{type: ?string, label: string, requirement: string, reason: string, state: string, supplied: bool}>
     */
    public function documentRequirements(Property $property): array
    {
        $documents = $property->ownershipDocuments()->latest('id')->get()->groupBy(fn (OwnershipDocument $document) => $document->document_type?->value);
        $relationships = $property->owners()->pluck('relationship')->map(fn ($relationship) => $relationship instanceof OwnerRelationship ? $relationship : OwnerRelationship::tryFrom((string) $relationship));
        $tenure = $property->tenure;
        $structure = $property->ownership_structure;
        $titled = $tenure === null || in_array($tenure, [LandTenure::Freehold, LandTenure::Leasehold], true);

        $rules = [
            [OwnershipDocumentType::NationalId, self::REQUIRED, 'Identity of the person submitting this listing.'],
            $titled
                ? [OwnershipDocumentType::TitleDeed, self::REQUIRED, 'Evidence of the registered freehold or leasehold interest.']
                : [OwnershipDocumentType::TitleDeed, self::CONDITIONAL, 'Supply any registration, allotment or adjudication record you hold. Staff will carry out specialised verification.'],
            [OwnershipDocumentType::OfficialSearch, self::STAFF_OBTAINS, 'Lamu Acres obtains or confirms the official search. Upload a recent search if you already hold one.'],
            $property->isPartialOffer() || in_array($property->subdivision_status, [SubdivisionStatus::InProgress, SubdivisionStatus::Registered], true)
                ? [OwnershipDocumentType::SurveyPlan, self::CONDITIONAL, 'Requested because only part of the parcel is offered.']
                : [OwnershipDocumentType::SurveyPlan, self::OPTIONAL, 'Helps staff confirm boundaries.'],
            [OwnershipDocumentType::MarketingAuthority, self::OPTIONAL, 'Your marketing authority is submitted on this page. Attach a signed copy if you have one.'],
            $structure === OwnershipStructure::Joint || $relationships->contains(OwnerRelationship::CoOwner)
                ? [OwnershipDocumentType::CoOwnerAuthorization, self::CONDITIONAL, 'Requested because the land is jointly owned.']
                : null,
            [OwnershipDocumentType::SpousalConsent, self::STAFF_TO_DETERMINE, 'Staff will confirm whether spousal consent applies to this disposition.'],
            $relationships->contains(OwnerRelationship::Authorized)
                ? [OwnershipDocumentType::PowerOfAttorney, self::CONDITIONAL, 'Requested because you act for the owner. It must be legally effective for this transaction.']
                : null,
            $structure === OwnershipStructure::Company
                ? [OwnershipDocumentType::CompanyResolution, self::CONDITIONAL, 'Requested because a company owns the land.']
                : null,
            $structure === OwnershipStructure::Estate
                ? [OwnershipDocumentType::SuccessionAuthority, self::CONDITIONAL, 'Requested because the land belongs to an estate.']
                : null,
            $tenure === LandTenure::Leasehold
                ? [OwnershipDocumentType::HeadLease, self::CONDITIONAL, 'Requested because the land is leasehold.']
                : null,
            $tenure === LandTenure::Leasehold
                ? [OwnershipDocumentType::LandRates, self::CONDITIONAL, 'Land rent and rates evidence for the leasehold interest.']
                : [OwnershipDocumentType::LandRates, self::OPTIONAL, 'Helpful now. Rates clearance is confirmed before completion.'],
            [OwnershipDocumentType::Other, self::OPTIONAL, 'Any other document that supports your ownership or authority.'],
        ];

        $requirements = [];

        foreach (array_filter($rules) as [$type, $requirement, $reason]) {
            $latest = $documents->get($type->value)?->first();
            $supplied = $documents->get($type->value, collect())
                ->contains(fn (OwnershipDocument $document) => $document->state === VerificationItemState::NotApplicable
                    || ($document->hasFile() && $document->state !== VerificationItemState::Failed));

            $requirements[] = [
                'type' => $type->value,
                'label' => $type->label(),
                'requirement' => $requirement,
                'reason' => $reason,
                'state' => $latest?->state?->label() ?? VerificationItemState::NotSupplied->label(),
                'supplied' => $supplied,
            ];
        }

        if (($tenure !== null && $tenure->needsSpecialistVerification())
            || $structure === OwnershipStructure::CommunityGroup) {
            $requirements[] = [
                'type' => null,
                'label' => 'Specialised tenure verification',
                'requirement' => self::STAFF_TO_DETERMINE,
                'reason' => 'Community, adjudication-stage or unclassified land needs staff to confirm which records apply.',
                'state' => 'Staff review',
                'supplied' => false,
            ];
        }

        if ($property->land_use === LandUse::Agricultural) {
            $requirements[] = [
                'type' => null,
                'label' => 'Land Control Board consent',
                'requirement' => self::AT_COMPLETION,
                'reason' => 'May be needed for agricultural land at the transaction stage. It is not needed to list.',
                'state' => 'Not needed yet',
                'supplied' => false,
            ];
        }

        return $requirements;
    }

    public function verifyLink(PropertyOwner $owner, User $staff, ?float $share = null): PropertyOwner
    {
        $owner->status = OwnerLinkStatus::Verified;
        $owner->verified_at = now();
        $owner->verified_by = $staff->id;
        if ($share !== null) {
            $owner->share_percent = $share;
        }
        $owner->save();

        return $owner;
    }

    public function addOwner(Property $property, User $seller, OwnerRelationship $relationship, User $staff): PropertyOwner
    {
        $owner = $property->owners()->firstOrNew(['user_id' => $seller->id]);
        $owner->relationship = $relationship;
        $owner->status = OwnerLinkStatus::Verified;
        $owner->verified_at = now();
        $owner->verified_by = $staff->id;
        $owner->save();

        return $owner;
    }

    /**
     * Store a seller document privately. The linked checklist item moves to Received, never Verified.
     */
    public function storeDocument(Property $property, User $seller, UploadedFile $file, OwnershipDocumentType $type = OwnershipDocumentType::Other): OwnershipDocument
    {
        $path = $file->store('ownership/'.$property->id, 'local');

        $document = $property->ownershipDocuments()->create([
            'user_id' => $seller->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => 'local',
            'document_type' => $type,
            'state' => VerificationItemState::Received,
        ]);

        $category = $type->category();

        if ($category !== null) {
            $this->verification->seed($property);
            $item = $property->verificationItems()->where('category', $category)->first();

            if ($item !== null && $item->state === VerificationItemState::NotSupplied) {
                $item->state = VerificationItemState::Received;
                $item->evidence_note = 'Seller uploaded '.Str::lower($type->label()).'. Not yet reviewed.';
                $item->save();
                $this->verification->recalculate($property);
            }
        }

        return $document;
    }

    /**
     * Record a staff review outcome for a seller document.
     */
    public function reviewDocument(OwnershipDocument $document, User $staff, VerificationItemState $state, string $reason, ?string $expiresAt = null): OwnershipDocument
    {
        if (strlen(trim($reason)) < 8) {
            throw ValidationException::withMessages(['reason' => 'Record the reason for this decision.']);
        }

        if ($state === VerificationItemState::NotSupplied || ($state === VerificationItemState::Verified && ! $document->hasFile())) {
            throw ValidationException::withMessages(['state' => 'A document without a file cannot be marked verified.']);
        }

        $from = $document->state;
        $document->fill([
            'state' => $state,
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
            'review_note' => trim($reason),
            'expires_at' => $expiresAt,
        ])->save();

        PropertyAudit::query()->create([
            'property_id' => $document->property_id,
            'actor_id' => $staff->id,
            'action' => 'ownership_document_reviewed',
            'changes' => [
                'document' => $document->id,
                'type' => $document->document_type?->value,
                'from' => $from?->value,
                'to' => $state->value,
                'reason' => trim($reason),
            ],
        ]);

        return $document;
    }

    /**
     * Record that a document type does not apply to this listing, with the staff reason.
     */
    public function markDocumentNotApplicable(Property $property, OwnershipDocumentType $type, User $staff, string $reason): OwnershipDocument
    {
        if (strlen(trim($reason)) < 8) {
            throw ValidationException::withMessages(['reason' => 'Record why this document does not apply.']);
        }

        $document = $property->ownershipDocuments()->create([
            'user_id' => $staff->id,
            'document_type' => $type,
            'state' => VerificationItemState::NotApplicable,
            'reviewed_by' => $staff->id,
            'reviewed_at' => now(),
            'review_note' => trim($reason),
        ]);

        PropertyAudit::query()->create([
            'property_id' => $property->id,
            'actor_id' => $staff->id,
            'action' => 'ownership_document_not_applicable',
            'changes' => ['type' => $type->value, 'reason' => trim($reason)],
        ]);

        return $document;
    }

    public function submitAuthority(Property $property, User $seller, string $terms, AuthorityScope $scope = AuthorityScope::Both, ?string $validUntil = null): MarketingAuthority
    {
        return $property->authorities()->create([
            'seller_id' => $seller->id,
            'status' => AuthorityStatus::Submitted,
            'terms' => $terms,
            'scope' => $scope,
            'valid_until' => $validUntil,
            'submitted_at' => now(),
        ]);
    }

    public function decideAuthority(MarketingAuthority $authority, AuthorityStatus $status, User $staff): MarketingAuthority
    {
        if (! in_array($status, [AuthorityStatus::Active, AuthorityStatus::Declined, AuthorityStatus::Withdrawn], true)) {
            throw ValidationException::withMessages(['status' => 'Choose an authority decision.']);
        }

        $authority->status = $status;
        $authority->decided_at = now();
        $authority->decided_by = $staff->id;
        $authority->save();

        return $authority;
    }

    public function instruct(Property $property, User $seller, SaleInstructionKind $kind, string $body, ?string $availability = null): SaleInstruction
    {
        $text = strtolower($body);

        if (in_array($availability, ['sold', 'leased'], true) || str_contains($text, 'mark sold') || str_contains($text, 'mark leased')) {
            throw ValidationException::withMessages([
                'body' => 'A seller cannot mark this property sold or leased. Staff record completed transactions.',
            ]);
        }

        if ($availability !== null && ! in_array($availability, ['available', 'unavailable'], true)) {
            throw ValidationException::withMessages([
                'requested_availability' => 'Choose available or unavailable.',
            ]);
        }

        return $property->saleInstructions()->create([
            'seller_id' => $seller->id,
            'kind' => $kind,
            'body' => $body,
            'requested_availability' => $availability,
        ]);
    }

    public function respondToRequest(User $seller, PropertyInformationRequest $request, string $response): void
    {
        if (! $request->isOpen()) {
            throw ValidationException::withMessages(['response' => 'This request has already been answered.']);
        }

        app(PropertySubmission::class)->respond($seller, $request, $response, 'A landowner replied to a request for information.');
    }

    public function presentOffer(Offer $offer, User $staff, string $summary): OfferPresentation
    {
        return $offer->presentations()->create([
            'property_id' => $offer->property_id,
            'presented_by' => $staff->id,
            'amount' => $offer->amount,
            'summary' => $summary,
        ]);
    }

    public function respondToOffer(OfferPresentation $presentation, User $seller, string $response): OfferPresentation
    {
        if (! in_array($response, ['accept', 'decline'], true)) {
            throw ValidationException::withMessages(['seller_response' => 'Accept or decline the presented offer.']);
        }

        $presentation->seller_response = $response;
        $presentation->responded_at = now();
        $presentation->save();

        $presentation->property->saleInstructions()->create([
            'seller_id' => $seller->id,
            'kind' => SaleInstructionKind::OfferResponse,
            'body' => 'Seller '.$response.'d the presented offer of KSh '.number_format((int) $presentation->amount).'.',
        ]);

        return $presentation;
    }

    public function visibleToSeller(User $seller, Property $property): bool
    {
        if (! $seller->hasPermission('workspace.seller')) {
            return false;
        }

        return $property->owners()
            ->where('user_id', $seller->id)
            ->where('status', OwnerLinkStatus::Verified)
            ->whereIn('relationship', OwnerRelationship::sellerFacing())
            ->exists();
    }

    /**
     * The seller submitted this listing themselves, whatever its status.
     */
    public function isSubmitter(User $seller, Property $property): bool
    {
        return $seller->hasPermission('workspace.seller')
            && (int) $property->submitted_by === (int) $seller->id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fillListing(Property $property, array $data): void
    {
        $purpose = ListingPurpose::from((string) $data['purpose']);
        $section = $purpose === ListingPurpose::Sale ? ($data['sale'] ?? []) : ($data['lease'] ?? []);
        $basis = PriceBasis::tryFrom((string) ($section['price_basis'] ?? '')) ?? PriceBasis::forPurpose($purpose)[0];
        $acreage = filled($data['acreage'] ?? null) ? (float) $data['acreage'] : null;
        $offered = filled($data['offered_acreage'] ?? null) ? (float) $data['offered_acreage'] : null;
        $rate = filled($section['amount'] ?? null) ? (int) $section['amount'] : null;
        $locality = filled($data['locality_id'] ?? null)
            ? Locality::query()->with('county')->find($data['locality_id'])
            : null;
        $purposeTerms = $purpose === ListingPurpose::Sale ? SellerListingRequest::SALE_TERMS : SellerListingRequest::LEASE_TERMS;
        $terms = collect(SellerListingRequest::SHARED_TERMS)
            ->mapWithKeys(fn (string $key) => [$key => $data['terms'][$key] ?? null])
            ->merge(collect($purposeTerms)->mapWithKeys(fn (string $key) => [$key => $section[$key] ?? null]))
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => trim((string) $value))
            ->all();

        $property->fill([
            'title' => trim((string) $data['title']),
            'summary' => $data['summary'] ?? null,
            'description' => $data['description'] ?? null,
            'purpose' => $purpose,
            'property_type' => $data['property_type'] ?? null,
            'land_use' => $data['land_use'] ?? null,
            'county_id' => $locality?->county_id,
            'locality_id' => $locality?->id,
            'county' => $locality?->county?->name,
            'locality' => $locality?->name,
            'landmark' => $data['landmark'] ?? null,
            'access_description' => $data['access_description'] ?? null,
            'public_latitude' => $data['public_latitude'] ?? null,
            'public_longitude' => $data['public_longitude'] ?? null,
            'parcel_identifier' => $this->duplicates->normalizeParcel($data['parcel_identifier'] ?? null),
            'parcel_reference_type' => $data['parcel_reference_type'] ?? null,
            'survey_reference' => $data['survey_reference'] ?? null,
            'acreage' => $acreage,
            'offered_acreage' => $offered,
            'minimum_acreage' => filled($data['minimum_acreage'] ?? null) ? (float) $data['minimum_acreage'] : null,
            'subdivision_status' => $data['subdivision_status'] ?? null,
            'boundary_status' => $data['boundary_status'] ?? null,
            'tenure' => $data['tenure'] ?? null,
            'ownership_structure' => $data['ownership_structure'] ?? null,
            'occupation_status' => $data['occupation_status'] ?? null,
            'price_basis' => $basis,
            'price_rate' => $rate,
            'price_amount' => Property::computePriceAmount($basis, $rate, $offered ?? $acreage),
            'price_negotiable' => (bool) ($section['negotiable'] ?? false),
            'available_from' => $section['available_from'] ?? null,
            'lease_min_months' => $purpose === ListingPurpose::Lease ? ($section['min_months'] ?? null) : null,
            'lease_max_months' => $purpose === ListingPurpose::Lease ? ($section['max_months'] ?? null) : null,
            'listing_terms' => $terms === [] ? null : $terms,
        ]);

        $property->road_access = $data['road_access'] ?? $property->road_access ?? 'unknown';
        $property->restricted_latitude = $data['restricted_latitude'] ?? null;
        $property->restricted_longitude = $data['restricted_longitude'] ?? null;
    }

    /**
     * @param  array<string, string|null>  $amenities
     */
    private function syncAmenities(Property $property, array $amenities): void
    {
        $verified = $property->amenityStates()
            ->where('state', AmenityState::Verified)
            ->pluck('amenity')
            ->map(fn ($amenity) => $amenity instanceof Amenity ? $amenity->value : (string) $amenity)
            ->all();

        foreach (Amenity::cases() as $amenity) {
            if (in_array($amenity->value, $verified, true)) {
                continue;
            }

            $state = AmenityState::tryFrom((string) ($amenities[$amenity->value] ?? ''));

            if ($state === null) {
                $property->amenityStates()->where('amenity', $amenity->value)->delete();

                continue;
            }

            $property->amenityStates()->updateOrCreate(['amenity' => $amenity->value], ['state' => $state]);
        }
    }

    private function marketingRole(OwnerRelationship $relationship): MarketingRole
    {
        return $relationship === OwnerRelationship::Authorized ? MarketingRole::AuthorizedMarketer : MarketingRole::Owner;
    }

    private function reference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.strtoupper(Str::random(8));
        } while (Property::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
