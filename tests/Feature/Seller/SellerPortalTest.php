<?php

namespace Tests\Feature\Seller;

use App\Enums\AuthorityStatus;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PropertyStatus;
use App\Models\County;
use App\Models\Locality;
use App\Models\MarketingAuthority;
use App\Models\Offer;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class SellerPortalTest extends FoundationTestCase
{
    public function test_only_a_verified_or_authorised_relationship_can_see_a_property(): void
    {
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $other = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties', 'staff.verification')->create();

        $this->actingAs($seller)
            ->post(route('seller.properties.store'), ['title' => 'Shela holding', 'purpose' => 'sale', 'relationship' => 'owner', 'acreage' => 4])
            ->assertRedirect();

        $property = Property::query()->where('title', 'Shela holding')->first();
        $this->assertSame(1, Property::query()->where('title', 'Shela holding')->count());

        $this->actingAs($seller)->get(route('seller.properties.index'))->assertOk()->assertSeeInOrder(['Drafts', 'Shela holding', 'Awaiting ownership verification']);
        $this->actingAs($other)->get(route('seller.properties.index'))->assertOk()->assertDontSee('Shela holding');
        $this->actingAs($other)->get(route('seller.properties.show', $property))->assertForbidden();

        $link = PropertyOwner::query()->where('property_id', $property->id)->first();
        $this->actingAs($staff)
            ->post(route('staff.owners.verify', $link), ['identity_consistency_confirmed' => 1, 'reason' => 'Test review: matched identity and authority.'])
            ->assertRedirect();

        $this->actingAs($seller)->get(route('seller.properties.index'))->assertOk()->assertSee('Shela holding');
        $this->actingAs($other)->get(route('seller.properties.index'))->assertOk()->assertDontSee('Shela holding');

        $before = Property::query()->count();
        $this->actingAs($staff)
            ->post(route('staff.properties.owners.store', $property), [
                'user_id' => $other->id,
                'relationship' => OwnerRelationship::Authorized->value,
            ])
            ->assertRedirect();

        $otherLink = $property->owners()->where('user_id', $other->id)->firstOrFail();
        $this->actingAs($staff)->post(route('staff.owners.verify', $otherLink), [
            'identity_consistency_confirmed' => 1, 'reason' => 'Test review: matched identity and representative authority.',
        ])->assertSessionHasNoErrors();
        $this->assertSame($before, Property::query()->count());
        $this->actingAs($other)->get(route('seller.properties.show', $property))->assertOk()->assertSee('Shela holding');

        PropertyOwner::query()->where('user_id', $other->id)->update(['status' => OwnerLinkStatus::Revoked]);
        $this->actingAs($other)->get(route('seller.properties.show', $property))->assertForbidden();
    }

    public function test_seller_instructions_do_not_sell_the_property_or_pay_commission(): void
    {
        Storage::fake('local');
        [$seller, $staff, $property] = $this->verifiedProperty();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'staff.buyers')->create();

        $this->actingAs($seller)
            ->post(route('seller.properties.photos.store', $property), ['photo' => UploadedFile::fake()->image('plot.jpg')])
            ->assertRedirect();

        foreach (['national_id' => 'national-id.pdf', 'title_deed' => 'title-deed.pdf'] as $type => $name) {
            $this->actingAs($seller)
                ->post(route('seller.properties.documents.store', $property), [
                    'document_type' => $type,
                    'document' => UploadedFile::fake()->create($name, 20, 'application/pdf'),
                ])
                ->assertRedirect();
        }

        $document = $property->ownershipDocuments()->where('document_type', 'title_deed')->first();
        $this->actingAs(User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create())
            ->get(route('seller.documents.download', $document))
            ->assertForbidden();

        $this->actingAs($seller)
            ->post(route('seller.properties.authority', $property), [
                'terms' => 'I authorise Lamu Acres to market this land on the stated terms.',
                'scope' => 'sale',
                'status' => AuthorityStatus::Active->value,
            ])
            ->assertRedirect();

        $authority = MarketingAuthority::query()->firstOrFail();
        $this->assertSame(AuthorityStatus::Submitted, $authority->status);

        $this->actingAs($seller)
            ->post(route('seller.properties.instructions', $property), [
                'kind' => 'listing',
                'body' => 'Please list this land for sale.',
            ])
            ->assertRedirect();

        $this->assertSame(PropertyStatus::Draft, $property->refresh()->status);

        $this->actingAs($seller)
            ->post(route('seller.properties.instructions', $property), [
                'kind' => 'availability',
                'body' => 'Please mark sold today.',
                'requested_availability' => 'sold',
            ])
            ->assertSessionHasErrors('requested_availability');

        $this->actingAs($seller)->post(route('seller.properties.submission', $property))->assertSessionHasNoErrors();
        $this->assertSame(PropertyStatus::Review, $property->refresh()->status);

        $this->clearCriticalVerification($property, $staff);
        $this->actingAs($staff)->put(route('staff.authorities.update', $authority), ['status' => 'active'])->assertRedirect();
        $this->actingAs($staff)->post(route('staff.properties.listing', $property))->assertSessionHasNoErrors();
        $this->assertSame(PropertyStatus::Published, $property->refresh()->status);

        $buyer = User::factory()->withRoles('buyer')->create();
        $offer = Offer::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'amount' => 2_000_000,
            'message' => 'Buyer private offer note.',
        ]);

        $this->actingAs($sales)
            ->post(route('staff.offers.present', $offer), ['summary' => 'An offer of KSh 2,000,000 has been received.'])
            ->assertRedirect();

        $presentation = $offer->presentations()->first();
        $this->actingAs($seller)
            ->post(route('seller.offers.respond', $presentation), ['seller_response' => 'accept'])
            ->assertRedirect();

        $property->refresh();
        $this->assertSame(PropertyStatus::Published, $property->status);
        $this->assertSame(0, PropertyTransaction::query()->count());
        $this->assertSame('submitted', $offer->refresh()->status->value);

        $this->actingAs($sales)
            ->post(route('staff.properties.transactions.store', $property), [
                'status' => 'in_progress',
                'amount' => 2000000,
                'statement' => 'Deposit recorded. No commission is payable from this screen.',
            ])
            ->assertRedirect();

        $this->actingAs($seller)
            ->get(route('seller.transactions.show', PropertyTransaction::query()->first()))
            ->assertOk()
            ->assertSee('Deposit recorded');

        $this->assertNotSame(PropertyStatus::Sold, $property->refresh()->status);

        $this->actingAs($seller)
            ->put(route('staff.properties.transition', $property), ['status' => PropertyStatus::Sold->value])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: Property}
     */
    private function verifiedProperty(): array
    {
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();

        $county = County::query()->create(['name' => 'Lamu', 'slug' => 'lamu', 'is_active' => true]);
        $locality = Locality::query()->create(['county_id' => $county->id, 'name' => 'Hindi', 'slug' => 'hindi', 'kind' => 'place', 'is_active' => true]);

        $this->actingAs($seller)->post(route('seller.properties.store'), [
            'purpose' => 'sale',
            'relationship' => 'owner',
            'title' => 'Hindi block',
            'summary' => 'Four acres of level farmland near Hindi.',
            'description' => 'Fenced farmland with a seasonal stream.',
            'property_type' => 'agricultural',
            'land_use' => 'agricultural',
            'county_id' => $county->id,
            'locality_id' => $locality->id,
            'parcel_identifier' => 'LAMU/HINDI/101',
            'parcel_reference_type' => 'title_number',
            'acreage' => 4,
            'tenure' => 'freehold',
            'ownership_structure' => 'individual',
            'sale' => ['price_basis' => 'total', 'amount' => 2_400_000],
        ])->assertSessionHasNoErrors();
        $property = Property::query()->where('title', 'Hindi block')->firstOrFail();
        $this->actingAs($staff)->post(route('staff.owners.verify', $property->owners()->first()), ['identity_consistency_confirmed' => 1, 'reason' => 'Test review: matched identity and authority.']);

        return [$seller, $staff, $property->refresh()];
    }
}
