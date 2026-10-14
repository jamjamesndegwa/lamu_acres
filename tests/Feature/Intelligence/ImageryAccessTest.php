<?php

namespace Tests\Feature\Intelligence;

use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\ImageryScene;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class ImageryAccessTest extends FoundationTestCase
{
    public function test_imagery_is_published_by_an_editor_and_served_only_when_redistribution_is_allowed(): void
    {
        Storage::fake('local');

        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $before = $this->scene($researcher, '2020-01-15');
        $after = $this->scene($researcher, '2024-06-01');

        $this->actingAs($editor)->post(route('staff.imagery.scenes.publish', $before))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.imagery.scenes.publish', $after))->assertRedirect();

        $this->actingAs($researcher)->post(route('staff.imagery.comparisons.store'), [
            'area_label' => 'Recorded road reserve',
            'before_scene_id' => $before->id,
            'after_scene_id' => $after->id,
            'observed_change' => 'Researcher saw a cleared strip along the recorded road reserve.',
        ])->assertRedirect();

        $comparison = ImageryComparison::query()->firstOrFail();

        $this->get(route('intelligence.imagery.show', $comparison))->assertNotFound();
        $this->actingAs($researcher)->post(route('staff.imagery.comparisons.submit', $comparison))->assertRedirect();
        $this->actingAs($researcher)->post(route('staff.imagery.comparisons.publish', $comparison))->assertForbidden();
        $this->actingAs($editor)->post(route('staff.imagery.comparisons.publish', $comparison))->assertRedirect();

        $file = $before->files()->firstOrFail();

        auth()->logout();

        $this->get(route('intelligence.imagery.show', $comparison))
            ->assertOk()
            ->assertSee('Recorded road reserve')
            ->assertSee('Jan 15, 2020')
            ->assertSee('Jun 1, 2024')
            ->assertDontSee('cleared strip');

        $this->get(route('intelligence.files.show', $file))->assertForbidden();

        $this->actingAs($subscriber)
            ->get(route('intelligence.imagery.show', $comparison))
            ->assertOk()
            ->assertSee('cleared strip')
            ->assertSee('not available for redistribution');

        $this->actingAs($subscriber)->get(route('intelligence.files.show', $file))->assertForbidden();

        $this->actingAs($editor)->put(route('staff.imagery.scenes.update', $before), $this->scenePayload('2020-01-15', true))->assertRedirect();
        $this->actingAs($editor)->put(route('staff.imagery.scenes.update', $after), $this->scenePayload('2024-06-01', true))->assertRedirect();

        $this->actingAs($subscriber)->get(route('intelligence.files.show', $file))->assertOk();
        auth()->logout();
        $this->get(route('intelligence.files.show', $file))->assertForbidden();
    }

    public function test_a_comparison_cannot_publish_until_both_scenes_are_released_and_can_name_a_project(): void
    {
        Storage::fake('local');

        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();

        $this->actingAs($researcher)->post(route('staff.projects.store'), [
            'name' => 'Named port record',
            'category' => 'port',
            'project_status' => 'proposed',
            'coverage' => 'Named reserve',
            'summary' => 'A sourced port record used only as a link.',
            'source_name' => 'County notice',
        ])->assertRedirect();

        $project = DevelopmentProject::query()->where('name', 'Named port record')->firstOrFail();
        $before = $this->scene($researcher, '2020-01-15');
        $after = $this->scene($researcher, '2024-06-01');

        $this->actingAs($researcher)->post(route('staff.imagery.comparisons.store'), [
            'area_label' => 'Named reserve',
            'before_scene_id' => $before->id,
            'after_scene_id' => $after->id,
            'development_project_id' => $project->id,
            'observed_change' => 'Researcher recorded a change along the named reserve.',
            'annotation' => 'Annotation stays with the researcher note.',
        ])->assertRedirect();

        $comparison = ImageryComparison::query()->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.imagery.comparisons.submit', $comparison))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.imagery.comparisons.publish', $comparison))->assertStatus(422);

        $this->actingAs($editor)->post(route('staff.imagery.scenes.publish', $before))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.imagery.scenes.publish', $after))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.imagery.comparisons.publish', $comparison))->assertRedirect();

        auth()->logout();

        $this->get(route('intelligence.imagery.show', $comparison))
            ->assertOk()
            ->assertDontSee('Named port record')
            ->assertDontSee('Annotation stays');

        $this->actingAs($subscriber)
            ->get(route('intelligence.imagery.show', $comparison))
            ->assertOk()
            ->assertSee('Named port record')
            ->assertSee('Annotation stays with the researcher note.');
    }

    private function scene(User $researcher, string $date): ImageryScene
    {
        $this->actingAs($researcher)->post(route('staff.imagery.scenes.store'), [
            ...$this->scenePayload($date, false),
            'image' => UploadedFile::fake()->image('scene.jpg'),
        ])->assertRedirect();

        $scene = ImageryScene::query()->latest('id')->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.imagery.scenes.submit', $scene))->assertRedirect();

        return $scene;
    }

    /**
     * @return array<string, mixed>
     */
    private function scenePayload(string $date, bool $redistribute): array
    {
        return [
            'provider' => 'Licensed archive',
            'acquired_on' => $date,
            'resolution' => '10 m',
            'coverage' => 'Recorded road reserve',
            'cloud_conditions' => 'Clear',
            'license_name' => 'Staff-held licence',
            'source_url' => 'https://example.com/scene-not-fetched',
            'redistribution_allowed' => $redistribute ? '1' : '0',
            'notes' => 'Held for review. The remote file was not downloaded.',
        ];
    }
}
