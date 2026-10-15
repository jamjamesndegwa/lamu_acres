<?php

namespace Tests\Feature\Inventory;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use Tests\Feature\Foundation\FoundationTestCase;

class PropertyWorkspaceTest extends FoundationTestCase
{
    public function test_staff_see_ordered_approval_steps_with_actions_and_outstanding_checks(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties', 'staff.verification')->create();
        $property = Property::factory()->create(['status' => PropertyStatus::Review]);

        $response = $this->actingAs($staff)->get(route('staff.properties.show', $property));

        $response->assertOk()
            ->assertSeeInOrder([
                'Staff approval workflow', 'Check listing details', 'Review seller documents',
                'Verify the ownership relationship', 'Confirm marketing authority',
                'Check public photographs', 'Complete the verification passport', '7. Approve and publish',
            ])
            ->assertSee('Publication is blocked.')
            ->assertSee('At least one public photograph is required.')
            ->assertSee(route('staff.properties.edit', $property), false)
            ->assertSee(route('staff.properties.media', $property), false);

        $steps = collect($response->viewData('approvalSteps'))->keyBy('key');
        $this->assertFalse($steps['photos']['complete']);
        $this->assertFalse($steps['documents']['complete']);
        $this->assertFalse($steps['verification']['complete']);
    }

    public function test_verification_step_clears_after_critical_checks_are_reviewed(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties', 'staff.verification')->create();
        $property = Property::factory()->create(['status' => PropertyStatus::Review]);
        $this->clearCriticalVerification($property, $staff);

        $response = $this->actingAs($staff)->get(route('staff.properties.show', [$property, 'tab' => 'verification']));

        $response->assertOk()->assertSee('Open verification desk')
            ->assertSee(route('staff.verification.show', $property), false);
        $step = collect($response->viewData('approvalSteps'))->firstWhere('key', 'verification');
        $this->assertTrue($step['complete']);
        $this->assertSame([], $step['issues']);
    }

    public function test_staff_property_screens_expose_the_workspace_tabs_and_review_queues(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $broker = User::factory()->withRoles('broker')->create();
        $property = Property::factory()->create(['title' => 'Workspace holding']);

        $this->actingAs($buyer)->get(route('staff.properties.show', $property))->assertForbidden();

        $this->actingAs($staff)
            ->get(route('staff.properties.index'))
            ->assertOk()
            ->assertSee('Add property')
            ->assertSee('Workspace holding')
            ->assertSee('Status')
            ->assertSee('Edit');

        $this->actingAs($staff)
            ->get(route('staff.properties.show', $property))
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('Media')
            ->assertSee('Owners')
            ->assertSee('Verification')
            ->assertSee('Marketing')
            ->assertSee('Enquiries')
            ->assertSee('Transactions')
            ->assertSee('History')
            ->assertSee('Change status')
            ->assertSee('Partial sale');

        $this->actingAs($staff)
            ->get(route('staff.properties.show', [$property, 'tab' => 'marketing']))
            ->assertOk()
            ->assertSee('Save attributes');

        $this->actingAs($staff)
            ->get(route('staff.properties.media', $property))
            ->assertOk()
            ->assertSee('Upload');

        $this->actingAs($staff)
            ->withHeader('Turbo-Frame', 'modal')
            ->get(route('staff.properties.show', [$property, 'dialog' => 'status']))
            ->assertOk()
            ->assertSee('Change status')
            ->assertSee('Review');

        $this->actingAs($staff)
            ->withHeader('Turbo-Frame', 'modal')
            ->get(route('staff.properties.show', [$property, 'dialog' => 'portion']))
            ->assertOk()
            ->assertSee('Partial sale')
            ->assertSee('acres remain');

        $pending = Property::factory()->create([
            'title' => 'Pending acre',
            'submitted_by' => $broker->id,
            'status' => PropertyStatus::Review,
        ]);
        $returned = Property::factory()->create([
            'title' => 'Returned acre',
            'submitted_by' => $broker->id,
            'status' => PropertyStatus::Review,
        ]);
        $returned->informationRequests()->create([
            'staff_id' => $staff->id,
            'question' => 'Need the title',
        ]);

        $this->actingAs($staff)
            ->get(route('staff.listings.index', ['queue' => 'pending']))
            ->assertOk()
            ->assertSee('Pending acre')
            ->assertDontSee('Returned acre');

        $this->actingAs($staff)
            ->get(route('staff.listings.index', ['queue' => 'returned']))
            ->assertOk()
            ->assertSee('Returned acre')
            ->assertDontSee('Pending acre');

        $this->actingAs($staff)
            ->get(route('staff.listings.show', $pending))
            ->assertOk()
            ->assertSee('Open property workspace');
    }
}
