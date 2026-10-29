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
use App\Enums\MessageEvent;
use App\Enums\OfferStatus;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\OwnershipDocumentType;
use App\Enums\OwnershipStructure;
use App\Enums\PartnerCapability;
use App\Enums\PriceBasis;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\SaleInstructionKind;
use App\Enums\SalesStage;
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
use App\Models\SalesTransaction;
use App\Models\User;
use App\Notifications\OfferPresentedNotification;
use App\Notifications\PropertySubmittedNotification;
use App\Services\Access\CapabilityReviewer;
use App\Services\Broker\DuplicateListingDetector;
use App\Services\Broker\PropertySubmission;
use App\Services\Buyer\InterestWorkflow;
use App\Services\Communications\MessageDesk;
use App\Services\Communications\SalesFinanceNotifier;
use App\Services\Communications\StaffDeskNotifier;
use App\Services\Inventory\PropertyInventory;
use App\Services\Sales\SaleModeTerms;
use App\Services\Sales\SalesPipeline;
use App\Services\Verification\VerificationDesk;
use App\Support\R2Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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
    /**
     * Register a new listing on behalf of a landowner (staff intake).
     *
     * @param  array<string, mixed>  $data
     */
    public function registerByStaff(User $staff, array $data): Property
    {
        return DB::transaction(function () use ($staff, $data): Property {
            $property = new Property;
            $this->fillListing($property, $data);
            $relationship = OwnerRelationship::tryFrom((string) ($data['owner_relationship'] ?? $data['relationship'] ?? ''))
                ?? OwnerRelationship::Owner;
            $ownerUserId = filled($data['owner_user_id'] ?? null) ? (int) $data['owner_user_id'] : null;
            $agencyInventory = $ownerUserId === null;

            $property->forceFill([
                'reference' => filled($data['reference'] ?? null) ? (string) $data['reference'] : $this->reference('LA'),
                'slug' => Str::slug((string) $data['title']).'-'.Str::lower(Str::random(5)),
                'status' => PropertyStatus::Draft,
                'availability' => PropertyAvailability::Available,
                'submitted_by' => $ownerUserId,
                'seller_name' => $agencyInventory
                    ? ($data['seller_name'] ?? 'Lamu Acres')
                    : ($data['seller_name'] ?? null),
                'seller_contact' => $data['seller_contact'] ?? null,
                'price_currency' => 'KES',
                'marketing_role' => $agencyInventory
                    ? MarketingRole::AuthorizedMarketer
                    : MarketingRole::from((string) $data['marketing_role']),
                'represented_by' => $staff->id,
            ]);
            $property->save();

            if ($ownerUserId !== null) {
                $property->owners()->create([
                    'user_id' => $ownerUserId,
                    'relationship' => $relationship,
                    'status' => OwnerLinkStatus::Pending,
                ]);
            }

            $this->syncAmenities($property, $data['amenities'] ?? []);
            $this->verification->seed($property);

            PropertyAudit::query()->create([
                'property_id' => $property->id,
                'actor_id' => $staff->id,
                'action' => 'registered by staff',
            ]);

            return $property;
        });
    }

    public function finalizeStaffRegistration(User $staff, Property $property): Property
    {
        abort_unless($staff->hasPermission('staff.properties'), 403);
        abort_unless($property->isStaffIntakeListing(), 422);

        return DB::transaction(function () use ($staff, $property): Property {
            $property->refresh();
            $gaps = $this->submissionGaps($property, requireDocuments: false);

            if ($gaps !== []) {
                throw ValidationException::withMessages($gaps);
            }

            if ($property->isStaffRepresentedListing()) {
                $owner = $property->owners()->firstOrFail();
                $this->verifyLink($owner, $staff, null, 'Staff intake: owner identity and authority confirmed at registration.', true);

                $scope = match ($property->purpose) {
                    ListingPurpose::Sale => AuthorityScope::Sale,
                    ListingPurpose::Lease => AuthorityScope::Lease,
                    default => AuthorityScope::Both,
                };

                $property->authorities()->create([
                    'seller_id' => (int) $property->submitted_by,
                    'status' => AuthorityStatus::Active,
                    'terms' => 'Staff-confirmed marketing authority recorded at land registration.',
                    'scope' => $scope,
                    'submitted_at' => now(),
                    'decided_at' => now(),
                    'decided_by' => $staff->id,
                ]);
            }

            $this->verification->recordStaffIntakeChecks($property, $staff);

            $from = $property->status;

            if ($property->status === PropertyStatus::Draft) {
                $this->inventory->transition($staff, $property, PropertyStatus::Review);
                $property->refresh();
            }

            $property = $this->inventory->publish($staff, $property);

            PropertyAudit::query()->create([
                'property_id' => $property->id,
                'actor_id' => $staff->id,
                'action' => 'approved',
                'from_status' => $from->value,
                'to_status' => PropertyStatus::Published->value,
                'changes' => ['source' => 'staff_intake'],
            ]);

            return $property;
        });
    }

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
            $previousMode = $property->exists ? $property->saleMode() : null;
            $this->fillListing($property, $data);
            $property->marketing_role = $this->marketingRole($relationship);
            $property->save();

            if ($previousMode !== null && $previousMode !== $property->saleMode()) {
                PropertyAudit::query()->create([
                    'property_id' => $property->id,
                    'actor_id' => $seller->id,
                    'action' => 'sale_mode_changed',
                    'changes' => [
                        'from' => $previousMode->value,
                        'to' => $property->saleMode()->value,
                    ],
                ]);

                $pipeline = app(SalesPipeline::class);
                SalesTransaction::query()
                    ->where('property_id', $property->id)
                    ->where('stage', '!=', SalesStage::Completed)
                    ->each(function (SalesTransaction $transaction) use ($pipeline, $seller, $previousMode, $property): void {
                        $pipeline->timeline(
                            $transaction,
                            $seller,
                            'Listing sale mode changed from '.$previousMode->label().' to '.$property->saleMode()->label().'. Review acreage and terms before progressing.',
                            true,
                        );
                    });
            }

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

        app(MessageDesk::class)->send($seller, MessageEvent::ListingSubmitted, [
            'name' => $seller->name,
            'title' => $property->title,
            'reference' => $property->reference,
            'summary' => 'Your land listing was submitted for staff review.',
            'url' => $property->submitterUrl(),
        ], 'property:'.$property->id.':seller-submit');

        app(StaffDeskNotifier::class)->notifyPermission(
            'staff.properties',
            MessageEvent::ListingStaffAlert,
            [
                'title' => $property->title,
                'reference' => $property->reference,
                'summary' => 'A landowner submitted land for review.',
                'url' => route('staff.listings.show', $property),
            ],
            'property:'.$property->id.':seller-staff',
            new PropertySubmittedNotification($property, 'A landowner submitted land for review.'),
        );

        return $property;
    }

    /**
     * Fields and evidence that must be in place before a draft can go to staff review.
     *
     * @return array<string, string>
     */
    public function submissionGaps(Property $property, bool $requireDocuments = true): array
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

        if ($property->allowsPartialPurchases() && $property->minimum_acreage === null) {
            $gaps['minimum_acreage'] = 'State the smallest portion you would sell.';
        }

        if ($property->allowsPartialPurchases() && $property->subdivision_status === null) {
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

        if ($requireDocuments) {
            foreach ($this->documentRequirements($property) as $requirement) {
                if ($requirement['requirement'] === self::REQUIRED && ! $requirement['supplied']) {
                    $gaps['documents.'.$requirement['type']] = 'Upload the '.Str::lower($requirement['label']).'.';
                }
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

    /** @return list<string> */
    public function ownerIdentityBlockers(PropertyOwner $owner, bool $requireConfirmation = true): array
    {
        $user = $owner->user;
        if ($user === null || ! $user->hasPermission('workspace.seller') || ! app(CapabilityReviewer::class)->hasCurrentCapability($user, PartnerCapability::Seller)) {
            return ['Complete and approve the linked seller identity application before confirming ownership.'];
        }
        $application = $user->capabilityApplications()->where('capability', PartnerCapability::Seller)->firstOrFail();
        $profile = $application->verification;
        $issues = [];
        if (in_array($profile['applicant_type'], ['authorized_representative', 'company_representative'], true) && $owner->relationship !== OwnerRelationship::Authorized) {
            $issues[] = 'The verified applicant is a representative. Correct the owner relationship and check the authority evidence.';
        }
        if ($requireConfirmation && ($owner->verified_at === null || (int) $owner->verified_by === (int) $owner->user_id
            || $owner->verified_at->lt(Carbon::parse($profile['identity']['verified_at']))
            || ! $owner->property->audits()->where('action', 'owner_identity_confirmed')->where('changes->owner_id', $owner->id)->exists())) {
            $issues[] = 'Staff must confirm this owner record against the current verified identity and property authority evidence on the Owners tab.';
        }

        return $issues;
    }

    public function verifyLink(PropertyOwner $owner, User $staff, ?float $share = null, string $reason = '', bool $identityConfirmed = false): PropertyOwner
    {
        abort_unless(! $staff->isSuspended() && $staff->id !== $owner->user_id
            && ($staff->hasPermission('staff.verification') || $staff->hasPermission('staff.properties')), 403);
        $issues = $this->ownerIdentityBlockers($owner, false);
        if (! $identityConfirmed || strlen(trim($reason)) < 8 || $issues !== []) {
            throw ValidationException::withMessages(['ownership' => implode(' ', $issues).' Confirm identity/authority consistency and record a review reason.']);
        }

        return DB::transaction(function () use ($owner, $staff, $share, $reason): PropertyOwner {
            $owner->status = OwnerLinkStatus::Verified;
            $owner->verified_at = now();
            $owner->verified_by = $staff->id;
            if ($share !== null) {
                $owner->share_percent = $share;
            }
            $owner->save();
            PropertyAudit::query()->create([
                'property_id' => $owner->property_id, 'actor_id' => $staff->id,
                'action' => 'owner_identity_confirmed', 'changes' => ['owner_id' => $owner->id, 'reason' => trim($reason)],
            ]);

            return $owner;
        });
    }

    public function addOwner(Property $property, User $seller, OwnerRelationship $relationship, User $staff): PropertyOwner
    {
        $owner = $property->owners()->firstOrNew(['user_id' => $seller->id]);
        $owner->relationship = $relationship;
        $owner->status = OwnerLinkStatus::Pending;
        $owner->verified_at = null;
        $owner->verified_by = null;
        $owner->save();

        return $owner;
    }

    /**
     * Store a seller document privately. The linked checklist item moves to Received, never Verified.
     */
    public function storeDocument(Property $property, User $seller, UploadedFile $file, OwnershipDocumentType $type = OwnershipDocumentType::Other): OwnershipDocument
    {
        $disk = R2Storage::privateUploadDisk();
        $path = R2Storage::store($file, 'ownership/'.$property->id, 'document', $disk);

        try {
            $document = $property->ownershipDocuments()->create([
                'user_id' => $seller->id,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'disk' => $disk,
                'mime' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'document_type' => $type,
                'state' => VerificationItemState::Received,
            ]);
        } catch (Throwable $throwable) {
            Storage::disk($disk)->delete($path);

            throw $throwable;
        }

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
        $offer->loadMissing('property');
        $presentation = $offer->presentations()->create([
            'property_id' => $offer->property_id,
            'presented_by' => $staff->id,
            'amount' => $offer->amount,
            'summary' => $summary,
        ]);

        $sellers = $offer->property->owners()
            ->where('status', OwnerLinkStatus::Verified)
            ->whereIn('relationship', OwnerRelationship::sellerFacing())
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        if ($sellers->isEmpty() && $offer->property->submitted_by !== null) {
            $submitter = User::query()->find($offer->property->submitted_by);
            if ($submitter !== null && $submitter->canUseSellerFeatures()) {
                $sellers = collect([$submitter]);
            }
        }

        try {
            Notification::send($sellers->unique('id'), new OfferPresentedNotification($presentation));
            app(SalesFinanceNotifier::class)->offerPresented($presentation);
        } catch (Throwable) {
            // Presentation remains valid if notification delivery fails.
        }

        return $presentation;
    }

    public function respondToOffer(OfferPresentation $presentation, User $seller, string $response): OfferPresentation
    {
        if (! in_array($response, ['accept', 'decline'], true)) {
            throw ValidationException::withMessages(['seller_response' => 'Accept or decline the presented offer.']);
        }

        $presentation->loadMissing('offer.presentations');

        if ($presentation->seller_response !== null) {
            throw ValidationException::withMessages(['seller_response' => 'This presentation has already been answered.']);
        }

        $latest = $presentation->offer->presentations()->latest('id')->first();
        if ($latest !== null && (int) $latest->id !== (int) $presentation->id) {
            throw ValidationException::withMessages(['seller_response' => 'A newer offer presentation is waiting for your response.']);
        }

        $presentation->seller_response = $response;
        $presentation->responded_at = now();
        $presentation->save();

        $presentation->property->saleInstructions()->create([
            'seller_id' => $seller->id,
            'kind' => SaleInstructionKind::OfferResponse,
            'body' => 'Seller '.$response.'d the presented offer of KSh '.number_format((int) $presentation->amount).'.',
        ]);

        app(InterestWorkflow::class)->reflectSellerPresentation(
            $presentation->offer,
            $response === 'accept' ? OfferStatus::Accepted : OfferStatus::Declined,
            $seller,
        );

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

        app(SaleModeTerms::class)->applyListingInput($property, $data);

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
            'offered_acreage' => $property->offered_acreage,
            'minimum_acreage' => $property->minimum_acreage,
            'subdivision_status' => $data['subdivision_status'] ?? null,
            'boundary_status' => $data['boundary_status'] ?? null,
            'tenure' => $data['tenure'] ?? null,
            'ownership_structure' => $data['ownership_structure'] ?? null,
            'occupation_status' => $data['occupation_status'] ?? null,
            'price_basis' => $basis,
            'price_rate' => $rate,
            'price_amount' => Property::computePriceAmount($basis, $rate, $property->offered_acreage ?? $offered ?? $acreage),
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
