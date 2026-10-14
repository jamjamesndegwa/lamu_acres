<?php

namespace Tests\Feature\Inventory;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;
use Tests\Feature\Foundation\FoundationTestCase;

class PropertyWorkspaceTest extends FoundationTestCase
{
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
