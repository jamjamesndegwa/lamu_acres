<?php

namespace Tests\Feature\Communications;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Enums\PartnerCapability;
use App\Enums\SaleMode;
use App\Jobs\DeliverMessage;
use App\Models\CapabilityApplication;
use App\Models\County;
use App\Models\Locality;
use App\Models\MessageDelivery;
use App\Models\Property;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Services\Broker\PropertySubmission;
use App\Services\Communications\MessageDesk;
use App\Services\Seller\SellerPortal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class TransactionalEmailCoverageTest extends FoundationTestCase
{
    public function test_welcome_email_is_queued_after_registration(): void
    {
        Bus::fake([DeliverMessage::class]);

        $this->post(route('register'), [
            'name' => 'New Member',
            'email' => 'new-member@example.test',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])->assertRedirect();

        $user = User::query()->where('email', 'new-member@example.test')->firstOrFail();

        Bus::assertDispatched(DeliverMessage::class);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $user->id)
                ->where('event', MessageEvent::AccountRegistered)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
    }

    public function test_password_reset_notification_is_queued_on_critical_queue(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Password::sendResetLink(['email' => $user->email]);

        Notification::assertSentTo($user, QueuedResetPassword::class);
    }

    public function test_password_change_confirmation_is_queued_on_critical_queue(): void
    {
        Bus::fake([DeliverMessage::class]);

        $user = User::factory()->withRoles('buyer')->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user)->put(route('buyer.profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect();

        Bus::assertDispatched(DeliverMessage::class, fn (DeliverMessage $job): bool => $job->queue === config('communications.queues.critical'));

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $user->id)
                ->where('event', MessageEvent::PasswordChanged)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
    }

    public function test_seller_application_submission_and_approval_queue_emails(): void
    {
        Mail::fake();
        Storage::fake('local');

        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.brokerage')->create();

        $this->actingAs($buyer)->post(route('account.applications.store', ['capability' => 'seller']), [
            'capability' => 'seller',
            'statement' => 'I hold land at Mokowe and provide identity evidence.',
            'legal_name' => 'Test Seller Applicant',
            'applicant_type' => 'individual_owner',
            'identity' => UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $application = $buyer->capabilityApplications()->firstOrFail();

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::ApplicationSubmitted)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );

        $this->actingAs($desk)->put(route('staff.applications.update', $application), [
            'status' => 'approved',
            'decision_reason' => 'Identity documents were accepted.',
            'identity_outcome' => 'verified',
        ])->assertRedirect();

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::ApplicationDecision)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
    }

    public function test_broker_application_submission_queues_email(): void
    {
        Storage::fake('local');

        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);

        $this->actingAs($buyer)->post(route('account.applications.store', ['capability' => 'broker']), [
            'capability' => 'broker',
            'statement' => 'I introduce buyers around Shela with current EARB credentials.',
            'legal_name' => 'Test Broker Applicant',
            'applicant_type' => 'individual',
            'earb_registration_number' => 'EARB-TEST-001',
            'certificate_expires_on' => now()->addYear()->toDateString(),
            'identity' => UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf'),
            'practising_certificate' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::ApplicationSubmitted)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
    }

    public function test_rejected_application_queues_decision_email(): void
    {
        Storage::fake('local');

        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.brokerage')->create();
        $application = CapabilityApplication::query()->create([
            'user_id' => $buyer->id,
            'capability' => PartnerCapability::Seller,
            'status' => CapabilityApplicationStatus::Pending,
            'statement' => 'Seller application pending review.',
        ]);

        $this->actingAs($desk)->put(route('staff.applications.update', $application), [
            'status' => 'rejected',
            'decision_reason' => 'Evidence did not match the declared ownership.',
        ])->assertRedirect();

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $buyer->id)
                ->where('event', MessageEvent::ApplicationDecision)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );

        $body = MessageDelivery::query()
            ->where('user_id', $buyer->id)
            ->where('event', MessageEvent::ApplicationDecision)
            ->where('channel', MessageChannel::Email)
            ->value('body');

        $this->assertStringNotContainsString('evidence/', (string) $body);
    }

    public function test_listing_submission_and_publication_queue_emails_without_duplicates(): void
    {
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create(['notify_by_email' => false]);
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $locality = $this->locality();
        Storage::fake('local');

        $response = $this->actingAs($broker)->post(route('broker.listings.store'), $this->brokerPayload($locality));
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $property = Property::query()->latest('id')->firstOrFail();

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $broker->id)
                ->where('event', MessageEvent::ListingSubmitted)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );

        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $owner = $property->owners()->create(['user_id' => $seller->id, 'relationship' => 'owner', 'status' => 'pending']);
        app(SellerPortal::class)->verifyLink($owner, $staff, null, 'Test review: owner identity and marketing instruction match.', true);
        $property->authorities()->create([
            'seller_id' => $seller->id,
            'scope' => 'both',
            'status' => 'active',
            'terms' => 'Test-only reviewed marketing authority for this property.',
        ]);
        $this->clearCriticalVerification($property, $staff);
        $this->attachPublicPhoto($property, $broker);

        app(PropertySubmission::class)->approve($staff, $property->fresh());
        app(PropertySubmission::class)->approve($staff, $property->fresh());

        $publishedEmails = MessageDelivery::query()
            ->where('user_id', $broker->id)
            ->where('event', MessageEvent::PropertyPublished)
            ->where('channel', MessageChannel::Email)
            ->count();

        $this->assertSame(1, $publishedEmails);
    }

    public function test_mandatory_email_is_not_blocked_by_notify_by_email(): void
    {
        $user = User::factory()->create(['notify_by_email' => false]);

        app(MessageDesk::class)->send($user, MessageEvent::ListingSubmitted, [
            'name' => $user->name,
            'title' => 'Plot',
            'summary' => 'Submitted.',
            'url' => route('home'),
        ], 'mandatory-listing:'.$user->id);

        $this->assertTrue(
            MessageDelivery::query()
                ->where('user_id', $user->id)
                ->where('channel', MessageChannel::Email)
                ->exists()
        );
    }

    public function test_marketing_email_still_requires_consent(): void
    {
        $user = User::factory()->create([
            'notify_by_email' => true,
            'marketing_email_consent' => false,
        ]);

        $this->assertFalse(
            app(MessageDesk::class)->allows($user, MessageChannel::Email, MessageEvent::MarketingBroadcast)
        );
    }

    public function test_deliver_message_is_not_dispatched_before_transaction_commit(): void
    {
        Bus::fake([DeliverMessage::class]);

        $user = User::factory()->create();

        try {
            DB::transaction(function () use ($user): void {
                app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, [
                    'name' => $user->name,
                    'title' => 'Welcome',
                    'summary' => 'Hello.',
                    'url' => route('home'),
                ], 'after-commit:'.$user->id);

                Bus::assertNotDispatched(DeliverMessage::class);

                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
    }

    public function test_account_suspension_email_uses_critical_queue(): void
    {
        Bus::fake([DeliverMessage::class]);

        $admin = User::factory()->withRoles('super_admin')->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.suspension.update', $user), [
            'action' => 'suspend',
            'suspension_reason' => 'Policy review in progress.',
        ])->assertRedirect();

        Bus::assertDispatched(DeliverMessage::class, fn (DeliverMessage $job): bool => $job->queue === config('communications.queues.critical'));
    }

    /**
     * @return array<string, mixed>
     */
    private function brokerPayload(Locality $locality): array
    {
        return [
            'title' => 'Transactional coverage plot',
            'summary' => 'A test listing for email coverage.',
            'description' => 'Test-only description.',
            'locality_id' => $locality->id,
            'acreage' => 4,
            'price_amount' => 5000000,
            'purpose' => 'sale',
            'property_type' => 'agricultural',
            'sale_mode' => SaleMode::Whole->value,
            'parcel_identifier' => 'LAMU/TX/1',
            'marketing_role' => 'authorized_marketer',
            'seller_name' => 'Private seller',
            'seller_contact' => '0700000000',
            'sale_mode' => 'whole',
            'photos' => [UploadedFile::fake()->image('plot.jpg')],
            'authority' => UploadedFile::fake()->create('authority.pdf', 10, 'application/pdf'),
        ];
    }

    private function locality(): Locality
    {
        $county = County::query()->create(['name' => 'Lamu', 'slug' => 'lamu-tx', 'is_active' => true]);

        return Locality::query()->create([
            'county_id' => $county->id,
            'name' => 'Mokowe',
            'slug' => 'mokowe-tx',
            'kind' => 'place',
            'is_active' => true,
        ]);
    }
}
