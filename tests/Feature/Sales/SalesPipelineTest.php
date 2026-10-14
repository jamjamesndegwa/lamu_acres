<?php

namespace Tests\Feature\Sales;

use App\Enums\PropertyStatus;
use App\Enums\SalesStage;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\SaleInstruction;
use App\Models\SalesTransaction;
use App\Models\User;
use Tests\Feature\Foundation\FoundationTestCase;

class SalesPipelineTest extends FoundationTestCase
{
    public function test_a_transaction_keeps_valid_stages_and_does_not_treat_acceptance_as_completion(): void
    {
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $seller = User::factory()->withRoles('seller')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $other = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create(['title' => 'Pipeline plot', 'acreage' => 10]);

        $this->actingAs($seller)->get(route('staff.sales.index'))->assertForbidden();
        $this->actingAs($buyer)->post(route('staff.sales.store'), [])->assertForbidden();

        $enquiry = Enquiry::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'message' => 'Please arrange a viewing.',
        ]);

        $this->actingAs($sales)
            ->post(route('staff.sales.store'), [
                'origin' => 'enquiry',
                'property_id' => $property->id,
                'buyer_id' => $other->id,
                'enquiry_id' => $enquiry->id,
                'acreage' => 10,
            ])
            ->assertRedirect();

        $transaction = SalesTransaction::query()->firstOrFail();
        $this->assertStringStartsWith('ST-', $transaction->reference);
        $this->assertSame($buyer->id, $transaction->buyer_id);
        $this->assertSame(SalesStage::Enquiry, $transaction->stage);

        $this->actingAs($sales)
            ->put(route('staff.sales.update', $transaction), [
                'lock_version' => $transaction->lock_version,
                'stage' => SalesStage::Completed->value,
            ])
            ->assertSessionHasErrors('stage');

        $this->assertSame(PropertyStatus::Published, $property->refresh()->status);

        $this->actingAs($sales)
            ->from(route('staff.sales.show', $transaction))
            ->put(route('staff.sales.update', $transaction), [
                'lock_version' => 0,
                'stage' => SalesStage::Viewing->value,
            ])
            ->assertRedirect();

        $this->actingAs($sales)
            ->put(route('staff.sales.update', $transaction), [
                'lock_version' => 0,
                'stage' => SalesStage::Offer->value,
            ])
            ->assertSessionHasErrors('lock_version');

        $transaction->refresh();
        $this->actingAs($sales)
            ->post(route('staff.sales.offers.store', $transaction), [
                'lock_version' => $transaction->lock_version,
                'amount' => 1500000,
                'seller_response' => 'accept',
            ])
            ->assertRedirect();

        $transaction->refresh();
        $this->assertSame(SalesStage::Viewing, $transaction->stage);
        $this->assertSame('accept', $transaction->offers()->first()->seller_response);
        $this->assertSame(PropertyStatus::Published, $property->refresh()->status);
        $this->assertDatabaseMissing('sales_audits', ['event' => 'sale_completed']);

        $instruction = SaleInstruction::query()->create([
            'property_id' => $property->id,
            'seller_id' => $seller->id,
            'kind' => 'general',
            'body' => 'Sell four acres if a buyer is found.',
        ]);

        $this->actingAs($sales)
            ->post(route('staff.sales.store'), [
                'origin' => 'instruction',
                'property_id' => $property->id,
                'sale_instruction_id' => $instruction->id,
                'acreage' => 4,
            ])
            ->assertRedirect();

        $this->assertNull(SalesTransaction::query()->where('sale_instruction_id', $instruction->id)->first()->buyer_id);
    }
}
