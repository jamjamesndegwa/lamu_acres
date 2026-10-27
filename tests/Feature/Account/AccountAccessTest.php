<?php

namespace Tests\Feature\Account;

use App\Contracts\Auth\OtpSender;
use App\Models\BuyerProfile;
use App\Models\CapabilityApplication;
use App\Models\User;
use App\Notifications\AccountEmailChanged;
use App\Notifications\ApplicationOutcomeNotification;
use App\Services\Auth\OtpDeliveryResult;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class AccountAccessTest extends FoundationTestCase
{
    public function test_guests_start_partner_applications_at_registration_for_the_selected_capability(): void
    {
        foreach (['seller', 'broker'] as $capability) {
            $this->get(route('account.applications.create', ['capability' => $capability]))
                ->assertRedirect(route('register', ['capability' => $capability]));
            $this->get(route('register', ['capability' => $capability]))
                ->assertOk()
                ->assertSee('Register as a '.$capability)
                ->assertSee('name="capability" value="'.$capability.'"', false);
        }

        $this->get(route('broker-partners.apply'))->assertRedirect(route('register', ['capability' => 'broker']));
        $this->get(route('list-property.start'))->assertRedirect(route('register', ['capability' => 'seller']));
    }

    public function test_partner_registration_continues_to_the_application_without_granting_a_partner_role(): void
    {
        foreach (['seller', 'broker'] as $capability) {
            $this->post(route('register'), [
                'name' => 'Partner Applicant',
                'email' => $capability.'-applicant@example.test',
                'password' => 'SecurePassword123!',
                'password_confirmation' => 'SecurePassword123!',
                'capability' => $capability,
                'role' => $capability,
            ])->assertRedirect(route('account.applications.create', ['capability' => $capability]));

            $user = User::query()->where('email', $capability.'-applicant@example.test')->firstOrFail();
            $this->assertAuthenticatedAs($user);
            $this->assertTrue($user->hasRole('buyer'));
            $this->assertFalse($user->hasRole($capability));
            $this->assertSame(0, $user->capabilityApplications()->count());
            $this->post(route('logout'));
        }
    }

    public function test_existing_users_can_sign_in_and_continue_their_selected_partner_application(): void
    {
        $user = User::factory()->withRoles('buyer')->create(['password' => Hash::make('SecurePassword123!')]);

        foreach (['seller', 'broker'] as $capability) {
            $this->get(route('login', ['capability' => $capability]))
                ->assertOk()
                ->assertSee('name="capability" value="'.$capability.'"', false);
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'SecurePassword123!',
                'capability' => $capability,
            ])->assertRedirect(route('account.applications.create', ['capability' => $capability]));
            $this->assertAuthenticatedAs($user);
            $this->assertFalse($user->fresh()->hasRole($capability));
            $this->post(route('logout'));
        }
    }

    public function test_registration_does_not_accept_an_unrecognized_partner_capability(): void
    {
        $this->post(route('register'), [
            'name' => 'Invalid Applicant',
            'email' => 'invalid-partner@example.test',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'capability' => 'staff',
        ])->assertSessionHasErrors('capability');

        $this->assertDatabaseMissing('users', ['email' => 'invalid-partner@example.test']);
    }

    public function test_a_buyer_profile_belongs_only_to_that_account(): void
    {
        $owner = User::factory()->withRoles('buyer')->create();
        $other = User::factory()->withRoles('buyer')->create();

        $this->actingAs($owner)
            ->put(route('buyer.buying-profile.update'), [
                'looking_for' => 'Coastal acreage',
                'preferred_locality' => 'Shela',
            ])
            ->assertRedirect(route('buyer.dashboard'));

        $profile = BuyerProfile::query()->where('user_id', $owner->id)->firstOrFail();

        $this->assertTrue($owner->can('update', $profile));
        $this->assertFalse($other->can('update', $profile));
        $this->assertSame('Coastal acreage', $profile->looking_for);

        $this->actingAs($other)
            ->put(route('buyer.buying-profile.update'), [
                'looking_for' => 'Different search',
                'preferred_locality' => 'Hindi',
            ])
            ->assertRedirect(route('buyer.dashboard'));

        $this->assertSame('Coastal acreage', $profile->fresh()->looking_for);
        $this->assertSame('Different search', $other->buyerProfile->looking_for);
    }

    public function test_a_customer_cannot_approve_or_assign_their_own_partner_role(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->withRoles('buyer')->create();
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.brokerage')->create();
        $plainStaff = User::factory()->withRoles('staff')->create();

        $this->actingAs($buyer)->post(route('account.applications.store'), [
            'capability' => 'broker',
            'organization' => 'Coast Office',
            'statement' => 'I introduce buyers around Shela.',
            'legal_name' => 'Test Broker Applicant', 'applicant_type' => 'individual',
            'identity' => UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf'),
            'earb_registration_number' => 'TEST-EARB-123',
            'certificate_expires_on' => now()->addYear()->toDateString(),
            'practising_certificate' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('account.applications.index'));

        $application = CapabilityApplication::query()->where('user_id', $buyer->id)->firstOrFail();

        $this->assertSame('pending', $application->status->value);
        $this->assertFalse($buyer->fresh()->hasRole('broker'));
        $this->assertFalse($buyer->can('decide', $application));
        $this->assertFalse($plainStaff->can('decide', $application));
        $this->assertTrue($desk->can('decide', $application));

        $this->actingAs($buyer)
            ->put(route('staff.applications.update', $application), [
                'status' => 'approved',
                'decision_reason' => 'I approve myself.',
            ])
            ->assertForbidden();

        $this->actingAs($plainStaff)
            ->put(route('staff.applications.update', $application), [
                'status' => 'approved',
                'decision_reason' => 'Not my desk.',
            ])
            ->assertForbidden();

        $this->assertFalse($buyer->fresh()->hasRole('broker'));
    }

    public function test_staff_decisions_record_a_reason_and_notify_the_applicant(): void
    {
        Notification::fake();
        Storage::fake('local');

        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.brokerage')->create();
        $this->actingAs($buyer)->post(route('account.applications.store'), [
            'capability' => 'seller', 'statement' => 'I hold land at Mokowe and provide identity evidence.',
            'legal_name' => 'Test Seller Applicant', 'applicant_type' => 'individual_owner',
            'identity' => UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $application = $buyer->capabilityApplications()->firstOrFail();

        $this->actingAs($desk)->put(route('staff.applications.update', $application), [
            'status' => 'approved',
            'decision_reason' => 'Identity documents were accepted.',
            'identity_outcome' => 'verified', 'identity_reference' => 'TEST identity review reference',
            'identity_next_review_on' => now()->addYear()->toDateString(),
        ])->assertRedirect(route('staff.applications.index'));

        $application->refresh();

        $this->assertSame('approved', $application->status->value);
        $this->assertSame($desk->id, $application->decided_by);
        $this->assertNotNull($application->decided_at);
        $this->assertSame('Identity documents were accepted.', $application->decision_reason);
        $this->assertTrue($buyer->fresh()->hasRole('seller'));
        $this->assertTrue($buyer->fresh()->hasRole('buyer'));

        Notification::assertSentTo(
            $buyer,
            ApplicationOutcomeNotification::class,
            function (ApplicationOutcomeNotification $notification, array $channels): bool {
                return $channels === ['database']
                    && $notification->application->decision_reason === 'Identity documents were accepted.';
            },
        );

        $this->actingAs($desk)->put(route('staff.applications.update', $application), [
            'status' => 'suspended',
            'decision_reason' => 'The instruction was withdrawn.',
        ])->assertRedirect();

        $this->assertFalse($buyer->fresh()->hasRole('seller'));
        $this->assertSame('suspended', $application->fresh()->status->value);
    }

    public function test_a_reviewer_cannot_decide_their_own_application(): void
    {
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.brokerage')->create();
        $application = CapabilityApplication::query()->create([
            'user_id' => $desk->id,
            'capability' => 'broker',
            'status' => 'pending',
            'statement' => 'I also want a broker workspace.',
        ]);

        $this->assertFalse($desk->can('decide', $application));

        $this->actingAs($desk)
            ->put(route('staff.applications.update', $application), [
                'status' => 'approved',
                'decision_reason' => 'Approving myself.',
            ])
            ->assertForbidden();

        $this->assertFalse($desk->fresh()->hasRole('broker'));
    }

    public function test_suspension_revokes_access_and_cannot_be_applied_to_yourself(): void
    {
        $admin = User::factory()->withRoles('super_admin')->create();
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($admin)
            ->put(route('admin.users.suspension.update', $admin), [
                'action' => 'suspend',
                'suspension_reason' => 'Not allowed.',
            ])
            ->assertForbidden();

        $this->actingAs($buyer)->get('/buyer')->assertOk();

        $buyer->forceFill([
            'suspended_at' => now(),
            'suspension_reason' => 'Account disabled.',
        ])->save();

        $this->get('/buyer')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post('/login', [
            'email' => $buyer->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertNull($admin->fresh()->suspended_at);
    }

    public function test_password_reset_does_not_open_a_session(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $token = Password::broker()->createToken($buyer);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $buyer->email,
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertTrue(Hash::check('new-password-1', $buyer->fresh()->password));
    }

    public function test_contact_changes_stay_unverified_until_they_are_confirmed(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create([
            'email' => 'owner@example.com',
            'phone' => '0700000001',
            'phone_verified_at' => now(),
        ]);

        $this->actingAs($buyer)->put(route('buyer.profile.update'), [
            'name' => 'Owner',
            'email' => 'next@example.com',
            'phone' => '0700000002',
            'notify_by_email' => '1',
        ])->assertSessionHasErrors('phone');

        $buyer->refresh();
        $this->assertSame('0700000001', $buyer->phone);
        $this->assertNull($buyer->pending_phone);
        $this->assertSame('owner@example.com', $buyer->email);

        $sender = new class implements OtpSender
        {
            public string $code = '';

            public function available(): bool
            {
                return true;
            }

            public function send(string $phone, string $code): OtpDeliveryResult
            {
                $this->code = $code;

                return new OtpDeliveryResult(delivered: true, provider: 'test');
            }
        };
        $this->app->instance(OtpSender::class, $sender);

        $this->actingAs($buyer)->put(route('buyer.profile.update'), [
            'name' => 'Owner',
            'email' => 'next@example.com',
            'phone' => '0700000002',
            'notify_by_email' => '1',
        ])->assertRedirect();

        $buyer->refresh();
        $this->assertSame('next@example.com', $buyer->email);
        $this->assertNull($buyer->email_verified_at);
        $this->assertSame('0700000001', $buyer->phone);
        $this->assertSame('0700000002', $buyer->pending_phone);
        Notification::assertSentTo($buyer, AccountEmailChanged::class);

        $this->actingAs($buyer)->post(route('account.phone.confirm'), [
            'code' => $sender->code,
        ])->assertRedirect();

        $buyer->refresh();
        $this->assertSame('0700000002', $buyer->phone);
        $this->assertNull($buyer->pending_phone);
        $this->assertNotNull($buyer->phone_verified_at);

        $url = (new AccountEmailChanged)->verificationUrl($buyer);
        $this->actingAs($buyer)->get($url)->assertRedirect();
        $this->assertNotNull($buyer->fresh()->email_verified_at);
    }

    public function test_password_reset_links_are_sent_for_password_accounts(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();

        $this->post(route('password.email'), [
            'email' => $buyer->email,
        ])->assertRedirect();

        Notification::assertSentTo($buyer, ResetPassword::class);
    }

    public function test_the_account_menu_shows_the_role_and_stores_a_profile_photo(): void
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            $this->markTestSkipped('GD is required to store a profile photo.');
        }

        $broker = User::factory()->withRoles('broker')->create(['name' => 'Amina Hassan']);

        $this->actingAs($broker)
            ->get('/broker')
            ->assertOk()
            ->assertSee('aria-label="Account menu"', false)
            ->assertSee('Broker')
            ->assertSee('Profile')
            ->assertSee('Logout');

        Storage::fake('local');

        $this->actingAs($broker)
            ->post(route('account.avatar.store'), [
                'avatar' => UploadedFile::fake()->image('face.jpg', 40, 40),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Profile photo updated.');

        $broker->refresh();
        $this->assertNotNull($broker->avatar_path);
        Storage::disk('local')->assertExists($broker->avatar_path);

        $this->actingAs($broker)
            ->get(route('account.avatar.show'))
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');

        $this->actingAs($broker)
            ->delete(route('account.avatar.destroy'))
            ->assertRedirect()
            ->assertSessionHas('status', 'Profile photo removed.');

        $this->assertNull($broker->fresh()->avatar_path);
    }
}
