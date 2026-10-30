<?php

namespace Tests\Feature\Sales;

use App\Enums\OfferStatus;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\SaleMode;
use App\Enums\SalesOrigin;
use App\Models\Enquiry;
use App\Models\Offer;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\SaleInstruction;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Sales\SalesCreateContext;
use Tests\Feature\Foundation\FoundationTestCase;

class SalesCreateContextTest extends FoundationTestCase
{
    public function test_context_surfaces_sellers_matching_offers_and_suggestions(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $seller = User::factory()->withRoles('seller')->create(['name' => 'Plot Owner']);
        $buyer = User::factory()->withRoles('buyer')->create(['name' => 'Interested Buyer']);
        $property = Property::factory()->published()->create([
            'title' => 'Context plot',
            'acreage' => 12,
            'offered_acreage' => 12,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
        ]);

        PropertyOwner::query()->create([
            'property_id' => $property->id,
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);

        Enquiry::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'message' => 'Tell me more.',
        ]);

        Offer::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'amount' => 3_500_000,
            'requested_acreage' => 4,
            'status' => OfferStatus::Submitted,
        ]);

        SaleInstruction::query()->create([
            'property_id' => $property->id,
            'seller_id' => $seller->id,
            'kind' => 'general',
            'body' => 'Sell when ready.',
        ]);

        $context = app(SalesCreateContext::class)->assemble($property->id, $buyer->id, null);

        $this->assertSame('Plot Owner', $context['sellers'][0]['name']);
        $this->assertSame($seller->id, $context['sellers'][0]['user_id']);
        $this->assertSame($seller->id, $context['suggestions']['seller_id']);
        $this->assertCount(1, $context['matching_offers']);
        $this->assertSame(3_500_000, $context['matching_offers'][0]['amount']);
        $this->assertSame('offer', $context['suggestions']['origin']);
        $this->assertSame(4.0, (float) $context['suggestions']['acreage']);

        $this->actingAs($staff)
            ->get(route('staff.sales.create.context', [
                'property_id' => $property->id,
                'buyer_id' => $buyer->id,
            ]))
            ->assertOk()
            ->assertSee('Plot Owner')
            ->assertSee('Offers for this buyer on this property')
            ->assertSee('KSh 3,500,000');
    }

    public function test_create_page_includes_deal_intelligence_shell(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();

        $this->actingAs($staff)
            ->get(route('staff.sales.create'))
            ->assertOk()
            ->assertSee('Deal intelligence')
            ->assertSee('data-sales-create', false)
            ->assertSee('data-sales-create-seller', false);
    }

    public function test_opening_listed_property_sale_without_linked_records(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create();

        $this->actingAs($staff)
            ->post(route('staff.sales.store'), [
                'origin' => SalesOrigin::Listed->value,
                'property_id' => $property->id,
            ])
            ->assertRedirect();

        $transaction = SalesTransaction::query()->firstOrFail();
        $this->assertSame(SalesOrigin::Listed, $transaction->origin);
        $this->assertNull($transaction->sale_instruction_id);
    }

    public function test_opening_from_enquiry_origin_without_enquiry_opens_transaction(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $property = Property::factory()->published()->create();

        $this->actingAs($staff)
            ->from(route('staff.sales.create'))
            ->post(route('staff.sales.store'), [
                'origin' => 'enquiry',
                'property_id' => $property->id,
            ])
            ->assertRedirect();

        $this->assertSame(1, SalesTransaction::query()->count());
        $transaction = SalesTransaction::query()->firstOrFail();
        $this->assertNull($transaction->enquiry_id);
        $this->assertNull($transaction->buyer_id);
    }
}
