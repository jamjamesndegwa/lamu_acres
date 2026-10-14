<?php

namespace App\Services\Seller;

use App\Enums\AuthorityStatus;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\SaleInstructionKind;
use App\Models\MarketingAuthority;
use App\Models\Offer;
use App\Models\OfferPresentation;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\SaleInstruction;
use App\Models\User;
use App\Services\Verification\VerificationDesk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerPortal
{
    public function __construct(private VerificationDesk $verification) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(User $seller, array $data): Property
    {
        $property = Property::query()->create([
            'reference' => $this->reference('SP'),
            'slug' => Str::slug((string) $data['title']).'-'.Str::lower(Str::random(5)),
            'title' => $data['title'],
            'county' => $data['county'] ?? 'Lamu',
            'locality' => $data['locality'] ?? null,
            'acreage' => $data['acreage'] ?? null,
            'summary' => $data['summary'] ?? null,
            'status' => PropertyStatus::Draft,
            'availability' => PropertyAvailability::Unavailable,
            'submitted_by' => $seller->id,
        ]);

        $property->owners()->create([
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Pending,
        ]);

        $this->verification->seed($property);

        return $property;
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

    public function storeDocument(Property $property, User $seller, UploadedFile $file): void
    {
        $path = $file->store('ownership/'.$property->id, 'local');

        $property->ownershipDocuments()->create([
            'user_id' => $seller->id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => 'local',
        ]);
    }

    public function submitAuthority(Property $property, User $seller, string $terms): MarketingAuthority
    {
        return $property->authorities()->create([
            'seller_id' => $seller->id,
            'status' => AuthorityStatus::Submitted,
            'terms' => $terms,
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
        if ($availability === 'sold' || str_contains(strtolower($body), 'mark sold')) {
            throw ValidationException::withMessages([
                'body' => 'A seller cannot mark this property sold.',
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
            ->whereIn('relationship', [OwnerRelationship::Owner, OwnerRelationship::Authorized])
            ->exists();
    }

    private function reference(string $prefix): string
    {
        do {
            $reference = $prefix.'-'.strtoupper(Str::random(8));
        } while (Property::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
