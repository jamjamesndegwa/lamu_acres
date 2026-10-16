<?php

namespace Tests\Feature;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\DocumentAccessStatus;
use App\Enums\MessageEvent;
use App\Enums\OfferStatus;
use App\Enums\PropertyStatus;
use App\Enums\SalesStage;
use App\Enums\SourcingRequestStatus;
use App\Events\EnquirySubmitted;
use App\Jobs\DeliverMessage;
use App\Models\County;
use App\Models\Enquiry;
use App\Models\Locality;
use App\Models\MessageDelivery;
use App\Models\Property;
use App\Models\PropertyMatch;
use App\Models\SourcingRequest;
use App\Models\User;
use App\Notifications\ApplicationOutcomeNotification;
use App\Notifications\EnquiryAssignedNotification;
use App\Notifications\EnquiryResponseNotification;
use App\Notifications\EnquirySubmittedNotification;
use App\Notifications\PropertyApprovedNotification;
use App\Notifications\TemplatedDeskNotification;
use App\Services\Buyer\InterestWorkflow;
use App\Services\Communications\MessageDesk;
use App\Services\Inventory\PropertyInventory;
use App\Services\Sales\SalesPipeline;
use App\Services\Seller\SellerPortal;
use App\Services\Staff\SourcingDesk;
use App\Services\Verification\VerificationDesk;
use App\Services\Verification\VerificationOperations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Feature\Foundation\FoundationTestCase;

class UnifiedNotificationsTest extends FoundationTestCase
{
    #[TestWith(['buyer'])]
    #[TestWith(['seller'])]
    #[TestWith(['broker'])]
    #[TestWith(['staff'])]
    #[TestWith(['admin'])]
    #[TestWith(['investor'])]
    public function test_shared_bell_and_private_recent_history_in_every_workspace(string $workspace): void
    {
        $user = User::factory()->withRoles($workspace === 'investor' ? 'buyer' : $workspace)->withInvestorPass()->create();
        $other = User::factory()->withRoles('buyer')->create();
        $user->notify(new TemplatedDeskNotification('Your update', '<script>private()</script>', route('workspaces.index')));
        $other->notify(new TemplatedDeskNotification('Other account secret', 'Hidden'));

        $this->actingAs($user)->get(route($workspace.'.notifications.index'))
            ->assertOk()->assertSee('Notifications, 1 unread')->assertSee('data-unread-badge', false)
            ->assertSee('View all')->assertSee('Your update')->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>private()', false)->assertDontSee('Other account secret')
            ->assertSee('aria-expanded', false)->assertSee('keydown.escape', false);
    }

    public function test_zero_and_capped_unread_badges_and_seven_recent_items(): void
    {
        $user = User::factory()->withRoles('buyer')->create();
        $this->actingAs($user)->get(route('buyer.notifications.index'))->assertOk()->assertDontSee('data-unread-badge', false);
        for ($i = 0; $i < 101; $i++) {
            $user->notify(new TemplatedDeskNotification('Update '.$i, 'News'));
        }
        $response = $this->get(route('buyer.notifications.index'));
        $response->assertOk()->assertSee('Notifications, 101 unread')->assertSee('99+');
        $this->assertSame(7, substr_count(explode('</section>', explode('id="notification-dropdown"', $response->getContent())[1])[0], '<li>'));
        $this->assertCount(20, $response->viewData('notifications')->items());
    }

    public function test_open_marks_only_owned_notice_read_and_preserves_authorized_destination(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $enquiry = Enquiry::factory()->create(['buyer_id' => $buyer->id]);
        $buyer->notify(new EnquiryResponseNotification($enquiry->responses()->create(['user_id' => User::factory()->create()->id, 'body' => 'Staff response', 'visible_to_buyer' => true])));
        $notice = $buyer->notifications()->firstOrFail();
        $this->actingAs($buyer)->get(route('buyer.notifications.open', $notice->id))->assertRedirect(route('buyer.enquiries.show', $enquiry));
        $this->assertNotNull($notice->fresh()->read_at);
        $this->get(route('buyer.notifications.index'))->assertSee('Notifications, 0 unread');
        $other = User::factory()->withRoles('buyer')->create();
        $this->actingAs($other)->get(route('buyer.notifications.open', $notice->id))->assertNotFound();
    }

    #[TestWith(['https://evil.example/buyer'])]
    #[TestWith(['//evil.example/buyer'])]
    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(['/\\evil.example'])]
    #[TestWith(['/%2f%2fevil.example'])]
    #[TestWith(['/buyer%0d%0aLocation:evil'])]
    #[TestWith(['https://user:pass@localhost/buyer'])]
    #[TestWith(['/does-not-exist'])]
    #[TestWith(['/staff/applications'])]
    public function test_unsafe_missing_or_unauthorized_destinations_return_to_history(string $url): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $buyer->notify(new TemplatedDeskNotification('Update', 'News', $url));
        $this->actingAs($buyer)->get(route('buyer.notifications.open', $buyer->notifications()->first()->id))
            ->assertRedirect(route('buyer.notifications.index'))->assertSessionHas('status');
    }

    public function test_deleted_and_inaccessible_record_links_are_graceful(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $other = User::factory()->withRoles('buyer')->create();
        $enquiry = Enquiry::factory()->create(['buyer_id' => $other->id]);
        $buyer->notify(new EnquiryResponseNotification($enquiry->responses()->create(['user_id' => User::factory()->create()->id, 'body' => 'Staff response', 'visible_to_buyer' => true])));
        $notice = $buyer->notifications()->first();
        $this->actingAs($buyer)->get(route('buyer.notifications.open', $notice->id))->assertRedirect(route('buyer.notifications.index'));
        $enquiry->delete();
        $this->get(route('buyer.notifications.open', $notice->id))->assertRedirect(route('buyer.notifications.index'));
    }

    public function test_cross_workspace_action_uses_destination_permissions_and_switches_workspace(): void
    {
        $user = User::factory()->withRoles('buyer', 'staff')->withPermissions('staff.buyers')->create();
        $enquiry = Enquiry::factory()->create();
        $user->notify(new EnquiryAssignedNotification($enquiry));
        $notice = $user->notifications()->first();
        $response = $this->actingAs($user)->get(route('buyer.notifications.open', $notice->id));
        $response->assertRedirect(route('staff.enquiries.show', $enquiry));
        $this->get($response->headers->get('Location'))->assertOk()->assertSessionHas('workspace', 'staff');
        $user->revokePermissionTo('staff.buyers');
        $this->get(route('buyer.notifications.open', $notice->id))->assertRedirect(route('buyer.notifications.index'));
    }

    public function test_mark_all_read_is_scoped_and_does_not_resolve_pending_work(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create();
        $other = User::factory()->create();
        $enquiry = Enquiry::factory()->create(['status' => 'new']);
        $staff->notify(new EnquirySubmittedNotification($enquiry));
        $other->notify(new TemplatedDeskNotification('Other', 'News'));
        $this->actingAs($staff)->post(route('staff.notifications.read-all'))->assertRedirect(route('staff.notifications.index'));
        $this->assertSame(0, $staff->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
        $this->get(route('staff.notifications.index', ['filter' => 'attention']))->assertOk()->assertSee('New property enquiry');
        $enquiry->update(['status' => 'closed']);
        $response = $this->get(route('staff.notifications.index', ['filter' => 'attention']))->assertOk();
        $this->assertCount(0, $response->viewData('notifications')->items());
        $this->assertCount(0, $this->get(route('staff.notifications.index', ['filter' => 'unread']))->viewData('notifications')->items());
    }

    public function test_enquiry_department_and_assigned_recipient_receive_one_alert_per_event(): void
    {
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create(['notify_by_email' => false]);
        $unrelated = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $enquiry = Enquiry::factory()->create();
        EnquirySubmitted::dispatch($enquiry);
        EnquirySubmitted::dispatch($enquiry);
        $this->assertSame(1, $desk->notifications()->count());
        $this->assertSame(0, $unrelated->notifications()->count());
        $assigned = User::factory()->withRoles('staff')->create(['notify_by_email' => false]);
        $another = Enquiry::factory()->create(['assigned_to' => $assigned->id]);
        EnquirySubmitted::dispatch($another);
        $this->assertSame(1, $assigned->notifications()->count());
        $this->assertSame(1, $desk->notifications()->count());
    }

    public function test_document_requests_open_staff_review_and_decisions_only_notify_requesting_buyer(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.buyers')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $other = User::factory()->withRoles('buyer')->create();
        $match = PropertyMatch::query()->create(['buyer_id' => $buyer->id, 'property_id' => Property::factory()->published()->create()->id, 'status' => 'shortlisted', 'approved_at' => now()]);
        $workflow = app(InterestWorkflow::class);
        $request = $workflow->requestDocuments($match, $buyer, null);
        $notice = $staff->notifications()->firstOrFail();
        $this->actingAs($staff)->get(route('staff.notifications.open', $notice->id))->assertRedirect(route('staff.documents.index', ['record' => $request->id]).'#document-'.$request->id);
        $workflow->decideDocuments($request, DocumentAccessStatus::Approved, $staff);
        $this->assertSame(1, $buyer->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->actingAs($buyer)->get(route('buyer.notifications.open', $buyer->notifications()->first()->id))->assertRedirect(route('buyer.matches.show', $match));
        $this->assertCount(0, $this->actingAs($staff)->get(route('staff.notifications.index', ['filter' => 'attention']))->viewData('notifications')->items());
    }

    public function test_offer_status_delivery_is_single_and_links_to_selected_record(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $match = PropertyMatch::query()->create(['buyer_id' => $buyer->id, 'property_id' => Property::factory()->published()->create()->id, 'status' => 'shortlisted', 'approved_at' => now()]);
        $offer = app(InterestWorkflow::class)->submitOffer($match, $buyer, 1000000, null);
        app(InterestWorkflow::class)->transitionOffer($offer, OfferStatus::UnderReview, $staff);
        $this->assertSame(1, $buyer->notifications()->count());
        $this->assertSame(1, $staff->notifications()->count());
        $url = route('buyer.offers.index', ['record' => $offer->id]).'#offer-'.$offer->id;
        $this->actingAs($buyer)->get(route('buyer.notifications.open', $buyer->notifications()->first()->id))->assertRedirect($url);
    }

    public function test_sourcing_assignment_and_status_updates_do_not_repeat_unchanged_work(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sourcing')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $request = SourcingRequest::query()->create(['reference' => 'SR-NOTICE-TEST', 'buyer_id' => $buyer->id, 'status' => 'submitted', 'location_label' => 'Shela', 'centre_latitude' => -2.27, 'centre_longitude' => 40.9, 'radius_km' => 5, 'centre_wkt' => 'POINT(40.9 -2.27)', 'submitted_at' => now()]);
        $desk = app(SourcingDesk::class);
        $desk->assign($request, $staff, $staff);
        $desk->assign($request, $staff, $staff);
        $desk->updateStatus($request, SourcingRequestStatus::Closed, $staff);
        $desk->updateStatus($request, SourcingRequestStatus::Closed, $staff);
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame(1, $buyer->notifications()->count());
    }

    public function test_application_submission_only_alerts_authorized_reviewers_and_information_request_is_actionable(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        $reviewer = User::factory()->withRoles('staff')->withPermissions('staff.brokerage')->create();
        $other = User::factory()->withRoles('staff')->create();
        $this->actingAs($buyer)->post(route('account.applications.store'), [
            'capability' => 'seller', 'legal_name' => 'Test Applicant', 'applicant_type' => 'individual_owner',
            'statement' => 'Please review my application to list my own land.',
            'identity' => UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $application = $buyer->capabilityApplications()->firstOrFail();
        $this->assertSame(1, $reviewer->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->actingAs($reviewer)->get(route('staff.notifications.open', $reviewer->notifications()->first()->id))->assertRedirect(route('staff.applications.edit', $application));
        $application->update(['status' => CapabilityApplicationStatus::InformationRequired]);
        $buyer->notify(new ApplicationOutcomeNotification($application));
        $notice = $buyer->notifications()->first();
        $this->actingAs($buyer)->get(route('buyer.notifications.open', $notice->id))->assertRedirect(route('account.applications.create', ['capability' => 'seller']));
        $this->assertCount(1, $this->get(route('buyer.notifications.index', ['filter' => 'attention']))->viewData('notifications')->items());
        $application->update(['status' => CapabilityApplicationStatus::Approved]);
        $this->assertCount(0, $this->get(route('buyer.notifications.index', ['filter' => 'attention']))->viewData('notifications')->items());
    }

    public function test_retry_after_database_delivery_does_not_duplicate_inbox_entry(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create(['notify_by_email' => false]);
        app(MessageDesk::class)->send($buyer, MessageEvent::AccountRegistered, ['summary' => 'Welcome', 'url' => route('buyer.dashboard')], 'retry-test');
        $delivery = MessageDelivery::query()->where('user_id', $buyer->id)->firstOrFail();
        $delivery->update(['status' => 'failed']);
        app()->call([new DeliverMessage($delivery->idempotency_key), 'handle']);
        $this->assertSame(1, $buyer->notifications()->count());
    }

    public function test_seller_publication_and_return_for_correction_notify_only_the_submitter(): void
    {
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create(['notify_by_email' => false]);
        $other = User::factory()->withRoles('seller')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties', 'staff.verification')->create();
        $county = County::query()->create(['name' => 'Lamu', 'slug' => 'lamu', 'is_active' => true]);
        $locality = Locality::query()->create(['county_id' => $county->id, 'name' => 'Shela', 'slug' => 'shela', 'kind' => 'place', 'is_active' => true]);
        $property = Property::factory()->listedIn($locality)->create(['submitted_by' => $seller->id, 'status' => 'review']);
        $owner = $property->owners()->create(['user_id' => $seller->id, 'relationship' => 'owner', 'status' => 'pending']);
        app(SellerPortal::class)->verifyLink($owner, $staff, null, 'Test-only identity and authority review.', true);
        $property->authorities()->create(['seller_id' => $seller->id, 'scope' => 'sale', 'status' => 'active', 'terms' => 'Test-only current marketing instruction.']);
        $this->attachPublicPhoto($property, $seller);
        $this->clearCriticalVerification($property, $staff);
        $inventory = app(PropertyInventory::class);
        $inventory->transition($staff, $property, PropertyStatus::Draft);
        $this->assertCount(1, $this->actingAs($seller)->get(route('seller.notifications.index', ['filter' => 'attention']))->viewData('notifications')->items());
        $inventory->transition($seller, $property, PropertyStatus::Review);
        $this->assertCount(0, $this->get(route('seller.notifications.index', ['filter' => 'attention']))->viewData('notifications')->items());
        $this->actingAs($staff)->post(route('staff.properties.listing', $property))->assertSessionHasNoErrors();
        $this->assertSame('published', $property->fresh()->status->value);
        $this->assertSame(2, $seller->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $notice = $seller->notifications()->where('type', PropertyApprovedNotification::class)->firstOrFail();
        $this->actingAs($seller)->get(route('seller.notifications.open', $notice->id))->assertRedirect($property->submitterUrl());
    }

    public function test_verification_assignment_alerts_only_assignee_and_clears_when_critical_checks_are_complete(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.verification')->create();
        $other = User::factory()->withRoles('staff')->withPermissions('staff.verification')->create();
        $property = Property::factory()->create();
        app(VerificationDesk::class)->seed($property);
        $passport = $property->passport()->firstOrFail();
        $deadline = now()->addDays(5);
        $operations = app(VerificationOperations::class);
        $operations->assign($passport, $staff, $other, $deadline);
        $operations->assign($passport, $staff, $other, $deadline);
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $notice = $staff->notifications()->first();
        $this->actingAs($staff)->get(route('staff.notifications.open', $notice->id))->assertRedirect(route('staff.verification.show', $property));
        $this->assertCount(1, $this->get(route('staff.notifications.index', ['filter' => 'attention']))->viewData('notifications')->items());
        $this->clearCriticalVerification($property, $staff);
        $this->assertCount(0, $this->get(route('staff.notifications.index', ['filter' => 'attention']))->viewData('notifications')->items());
    }

    public function test_rolled_back_work_does_not_leave_an_inbox_alert_or_send_external_messages(): void
    {
        $user = User::factory()->withRoles('buyer')->create(['notify_by_email' => true]);
        Mail::fake();
        DB::beginTransaction();
        app(MessageDesk::class)->send($user, MessageEvent::AccountRegistered, ['summary' => 'Welcome', 'url' => route('buyer.dashboard')], 'rollback-test');
        DB::rollBack();
        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(0, MessageDelivery::query()->where('user_id', $user->id)->count());
        Mail::assertNothingSent();
    }

    public function test_sales_assignment_and_progress_open_authorized_records_and_ignore_repeat_assignment(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $agent = User::factory()->withRoles('staff')->withPermissions('staff.sales')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $enquiry = Enquiry::factory()->create(['buyer_id' => $buyer->id]);
        $pipeline = app(SalesPipeline::class);
        $sale = $pipeline->open($staff, ['origin' => 'enquiry', 'property_id' => $enquiry->property_id, 'enquiry_id' => $enquiry->id, 'acreage' => 1]);
        $sale = $pipeline->assign($sale, $agent, $staff, $sale->lock_version);
        $sale = $pipeline->assign($sale, $agent, $staff, $sale->lock_version);
        $this->assertSame(1, $agent->notifications()->count());
        $this->actingAs($agent)->get(route('staff.notifications.open', $agent->notifications()->first()->id))->assertRedirect(route('staff.sales.show', $sale));
        $pipeline->advance($sale, SalesStage::Viewing, $staff, $sale->lock_version);
        $this->assertSame(1, $buyer->notifications()->count());
        $this->actingAs($buyer)->get(route('buyer.notifications.open', $buyer->notifications()->first()->id))->assertRedirect(route('buyer.enquiries.show', $enquiry));
    }

    public function test_safe_query_parameters_survive_but_redirect_parameters_are_removed(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $enquiry = Enquiry::factory()->create(['buyer_id' => $buyer->id]);
        $buyer->notify(new TemplatedDeskNotification('Update', 'News', route('buyer.enquiries.show', $enquiry).'?tab=overview&redirect=https://evil.example'));
        $this->actingAs($buyer)->get(route('buyer.notifications.open', $buyer->notifications()->first()->id))->assertRedirect(route('buyer.enquiries.show', $enquiry).'?tab=overview');
    }
}
