<?php

namespace Tests\Feature\Verification;

use App\Enums\PropertyStatus;
use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Enums\VerificationLevel;
use App\Models\Property;
use App\Models\User;
use App\Models\VerificationReviewLog;
use App\Services\Verification\VerificationOperations;
use Tests\Feature\Foundation\FoundationTestCase;

class VerificationOperationsTest extends FoundationTestCase
{
    public function test_queues_follow_the_review_workflow_and_recalculate_the_level(): void
    {
        $officer = User::factory()->withRoles('staff')->withPermissions('staff.verification')->create();
        $manager = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();

        $this->actingAs($manager)->get(route('staff.verification.new'))->assertForbidden();
        $this->actingAs($seller)->get(route('staff.verification.missing'))->assertForbidden();

        $this->actingAs($seller)->post(route('seller.properties.store'), ['title' => 'Shela review', 'purpose' => 'sale', 'relationship' => 'owner']);
        $property = Property::query()->where('title', 'Shela review')->firstOrFail();

        $this->actingAs($officer)->get(route('staff.verification.new'))->assertOk()->assertSee('Shela review')->assertSee('New Reviews');
        $this->actingAs($officer)->get(route('staff.verification.missing'))->assertOk()->assertSee('Shela review');

        $summary = app(VerificationOperations::class)->summary();
        $this->assertSame(1, collect($summary)->firstWhere('key', 'new')['count']);

        $this->actingAs($officer)
            ->put(route('staff.verification.assign', $property), [
                'assigned_to' => $officer->id,
                'deadline_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $this->assertNotNull($property->passport()->first()->deadline_at);
        $this->actingAs($officer)->get(route('staff.verification.new'))->assertOk()->assertDontSee('Shela review');

        $item = $property->verificationItems()->where('category', VerificationCategory::TitleOwnership)->firstOrFail();
        $this->actingAs($officer)
            ->put(route('staff.verification.decide', $item), ['state' => VerificationItemState::Verified->value])
            ->assertSessionHasErrors('reason');
        $this->assertSame(VerificationItemState::NotSupplied, $item->refresh()->state);

        foreach ([VerificationCategory::TitleOwnership, VerificationCategory::OfficialSearch, VerificationCategory::SellerAuthority, VerificationCategory::Encumbrances] as $category) {
            $check = $property->verificationItems()->where('category', $category)->firstOrFail();
            $this->actingAs($officer)
                ->put(route('staff.verification.decide', $check), [
                    'state' => VerificationItemState::Verified->value,
                    'reason' => 'Compared with the registry extract on file.',
                    'review_due_at' => now()->addDays(10)->toDateString(),
                ])
                ->assertRedirect();
        }

        $this->assertSame(VerificationLevel::OwnershipChecked, $property->passport()->first()->refresh()->level);
        $this->assertSame('Compared with the registry extract on file.', VerificationReviewLog::query()->where('decision', 'verified')->first()->reason);
        $this->actingAs($officer)->get(route('staff.verification.completed'))->assertOk()->assertSee('Shela review');
        $this->actingAs($officer)->get(route('staff.verification.expiring'))->assertOk()->assertSee('Shela review');
        $this->actingAs($officer)->get(route('staff.verification.history', $property))->assertOk()->assertSee('Compared with the registry extract on file.');

        $title = $property->verificationItems()->where('category', VerificationCategory::TitleOwnership)->firstOrFail();
        $this->actingAs($officer)
            ->put(route('staff.verification.decide', $title), [
                'state' => VerificationItemState::Failed->value,
                'reason' => 'The registered name does not match the seller.',
            ])
            ->assertRedirect();

        $this->assertSame(VerificationLevel::Advertised, $property->passport()->first()->refresh()->level);
        $this->actingAs($officer)->get(route('staff.verification.exceptions'))->assertOk()->assertSee('Shela review');
        $this->actingAs($officer)->get(route('staff.verification.completed'))->assertOk()->assertDontSee('Shela review');
    }

    public function test_property_managers_see_blockers_before_a_listing_can_be_approved(): void
    {
        $manager = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $property = Property::factory()->create([
            'title' => 'Blocked listing',
            'status' => PropertyStatus::Review,
            'submitted_by' => $manager->id,
        ]);

        $this->actingAs($manager)
            ->get(route('staff.listings.show', $property))
            ->assertOk()
            ->assertSee('Verification passport has not been started.');

        $this->actingAs($manager)
            ->post(route('staff.listings.approve', $property))
            ->assertSessionHasErrors('listing');

        $this->assertNotSame('published', $property->refresh()->status->value);
    }
}
