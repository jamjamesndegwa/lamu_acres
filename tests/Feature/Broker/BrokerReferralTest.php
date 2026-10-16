<?php

namespace Tests\Feature\Broker;

use App\Enums\BrokerIntroductionStatus;
use App\Models\BrokerIntroduction;
use App\Models\BrokerIntroductionAudit;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use App\Notifications\ReferralDecidedNotification;
use App\Notifications\ReferralSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class BrokerReferralTest extends FoundationTestCase
{
    public function test_a_referral_requires_consent_and_stays_with_the_submitting_broker(): void
    {
        Notification::fake();
        Storage::fake('local');

        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create(['name' => 'Broker Ada']);
        $other = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create(['name' => 'Broker Ben']);
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.brokerage')->create();
        $outsider = User::factory()->withRoles('staff')->create();
        $property = Property::factory()->published()->create(['title' => 'Published Shela acre']);

        $this->get(route('broker.referrals.create'))->assertRedirect(route('login'));
        $this->actingAs($buyer)->get(route('broker.referrals.create'))->assertForbidden();
        $this->actingAs($outsider)->get(route('staff.referrals.index'))->assertForbidden();

        $this->actingAs($broker)
            ->post(route('broker.referrals.store'), $this->payload($property, [
                'consent_note' => 'Too short',
            ]))
            ->assertSessionHasErrors('consent_note');

        $this->actingAs($broker)
            ->post(route('broker.referrals.store'), $this->payload($property, [
                'consent_evidence' => null,
            ]))
            ->assertSessionHasErrors('consent_evidence');

        $draft = Property::factory()->create();

        $this->actingAs($broker)
            ->post(route('broker.referrals.store'), $this->payload($property, [
                'property_ids' => [$draft->id],
            ]))
            ->assertSessionHasErrors('property_ids');

        $this->actingAs($broker)
            ->post(route('broker.referrals.store'), $this->payload($property))
            ->assertRedirect();

        $referral = BrokerIntroduction::query()->where('broker_id', $broker->id)->firstOrFail();

        $this->assertStringStartsWith('RF-', $referral->reference);
        $this->assertSame(BrokerIntroductionStatus::Submitted, $referral->status);
        $this->assertFalse($referral->protectsCommission());
        $this->assertNotNull($referral->consent_path);
        Storage::disk('local')->assertExists($referral->consent_path);
        $this->assertTrue(BrokerIntroductionAudit::query()->where('broker_introduction_id', $referral->id)->where('action', 'submitted')->exists());
        Notification::assertSentTo($staff, ReferralSubmittedNotification::class);

        $this->actingAs($other)->get(route('broker.referrals.show', $referral))->assertNotFound();
        $this->actingAs($other)->get(route('broker.referrals.index'))->assertOk()->assertDontSee($referral->reference);

        $this->actingAs($staff)
            ->get(route('staff.referrals.show', $referral))
            ->assertOk()
            ->assertSee($referral->reference)
            ->assertSee('Broker Ada');
    }

    public function test_competing_introductions_stay_open_until_staff_accept_one(): void
    {
        Notification::fake();
        Storage::fake('local');

        $ada = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create(['name' => 'Broker Ada']);
        $ben = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create(['name' => 'Broker Ben']);
        $buyer = User::factory()->withRoles('buyer')->create([
            'name' => 'Buyer Claire',
            'email' => 'claire@example.com',
        ]);
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.brokerage')->create();
        $property = Property::factory()->published()->create(['title' => 'Published Shela acre']);

        $this->actingAs($buyer)->post(route('land.enquiries.store', $property), [
            'message' => 'I found this plot myself and would like a visit.',
        ])->assertRedirect();

        $this->actingAs($ada)->post(route('broker.referrals.store'), $this->payload($property))->assertRedirect();
        $first = BrokerIntroduction::query()->where('broker_id', $ada->id)->firstOrFail();

        $this->assertSame($buyer->id, $first->buyer_id);
        $this->assertContains('direct_relationship', collect($first->conflicts)->pluck('type'));
        $this->assertSame(BrokerIntroductionStatus::Submitted, $first->status);
        $this->assertFalse($first->protectsCommission());

        $this->actingAs($ada)->post(route('broker.referrals.store'), $this->payload($property, [
            'source' => 'Repeat introduction',
        ]))->assertRedirect();

        $repeat = BrokerIntroduction::query()->where('broker_id', $ada->id)->whereKeyNot($first->id)->firstOrFail();
        $this->assertContains('existing_combination', collect($repeat->conflicts)->pluck('type'));
        $this->assertSame(BrokerIntroductionStatus::Submitted, $repeat->status);

        $this->actingAs($ben)->post(route('broker.referrals.store'), $this->payload($property, [
            'source' => 'Second broker',
        ]))->assertRedirect();

        $competing = BrokerIntroduction::query()->where('broker_id', $ben->id)->firstOrFail();
        $this->assertContains('prior_introduction', collect($competing->conflicts)->pluck('type'));
        $this->assertContains('direct_relationship', collect($competing->conflicts)->pluck('type'));
        $this->assertSame(BrokerIntroductionStatus::Submitted, $competing->status);
        $this->assertFalse($competing->protectsCommission());
        $this->assertFalse($first->fresh()->protectsCommission());

        $this->actingAs($ben)
            ->get(route('broker.referrals.show', $competing))
            ->assertOk()
            ->assertSee('Another introduction already covers this buyer and property.')
            ->assertDontSee('Broker Ada')
            ->assertSee('Commission protection does not apply');

        $this->actingAs($staff)
            ->get(route('staff.referrals.show', $competing))
            ->assertOk()
            ->assertSee('Broker Ada');

        $this->actingAs($staff)->put(route('staff.referrals.update', $first), [
            'status' => BrokerIntroductionStatus::Disputed->value,
            'note' => 'Overlap with a later introduction.',
        ])->assertRedirect();

        $this->actingAs($staff)->put(route('staff.referrals.update', $competing), [
            'status' => BrokerIntroductionStatus::Accepted->value,
            'note' => 'Accepted under the proposed terms.',
        ])->assertRedirect();

        $this->assertSame(BrokerIntroductionStatus::Disputed, $first->fresh()->status);
        $this->assertFalse($first->fresh()->protectsCommission());
        $this->assertSame(BrokerIntroductionStatus::Submitted, $repeat->fresh()->status);
        $this->assertTrue($competing->fresh()->protectsCommission());
        Notification::assertSentTo($ben, ReferralDecidedNotification::class);

        $direct = Enquiry::query()->where('buyer_id', $buyer->id)->firstOrFail();
        $this->assertNull($direct->broker_introduction_id);

        $this->actingAs($buyer)->post(route('land.enquiries.store', $property), [
            'message' => 'Please arrange a second visit after the referral review.',
        ])->assertRedirect();

        $linked = Enquiry::query()->where('buyer_id', $buyer->id)->latest('id')->firstOrFail();
        $this->assertSame($competing->id, $linked->broker_introduction_id);
        $this->assertNotSame($first->id, $linked->broker_introduction_id);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Property $property, array $overrides = []): array
    {
        return array_merge([
            'buyer_name' => 'Buyer Claire',
            'buyer_email' => 'claire@example.com',
            'buyer_phone' => '0700000000',
            'property_ids' => [$property->id],
            'introduced_at' => now()->toDateString(),
            'source' => 'Office introduction',
            'commission_terms' => 'Two percent of the agreed sale price, payable on completion.',
            'consent_note' => 'The buyer agreed in writing that this broker may introduce them.',
            'consent_evidence' => UploadedFile::fake()->create('consent.pdf', 20, 'application/pdf'),
            'notes' => 'Introduced after a site conversation.',
        ], $overrides);
    }
}
