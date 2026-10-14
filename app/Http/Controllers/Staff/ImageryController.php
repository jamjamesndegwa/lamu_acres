<?php

namespace App\Http\Controllers\Staff;

use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Staff\Concerns\AuthorizesIntelligence;
use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\ImageryScene;
use App\Models\Locality;
use App\Services\Intelligence\IntelligenceFiles;
use App\Services\Intelligence\PublicationDesk;
use App\Support\ApprovalsReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ImageryController extends Controller
{
    use AuthorizesIntelligence;

    public function index(): View
    {
        return view('staff.intelligence.imagery.index', [
            'scenes' => ImageryScene::query()->latest()->paginate(20, ['*'], 'scenes'),
            'comparisons' => ImageryComparison::query()->with(['beforeScene', 'afterScene'])->latest()->paginate(20, ['*'], 'comparisons'),
        ]);
    }

    public function createScene(): View
    {
        return view('staff.intelligence.imagery.scene', [
            'scene' => new ImageryScene,
            'projects' => DevelopmentProject::query()->orderBy('name')->get(['id', 'name']),
            'localities' => $this->localities(),
        ]);
    }

    public function storeScene(Request $request, PublicationDesk $desk, IntelligenceFiles $files): RedirectResponse
    {
        $this->researcher($request);
        $data = $this->sceneData($request);
        $scene = ImageryScene::query()->create([
            ...$data,
            'reference' => $desk->reference('IM', ImageryScene::class),
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $request->user()->id,
            'redistribution_allowed' => $request->boolean('redistribution_allowed'),
        ]);

        if ($request->hasFile('image')) {
            $files->store($scene, $request->user(), $request->file('image'), 'scene');
        }

        $desk->audit($scene, $request->user(), 'created', null, PublicationStatus::Draft, 'The image was not fetched from a remote address.');

        return redirect()->route('staff.imagery.index')->with('status', 'Imagery scene saved as a draft.');
    }

    public function editScene(ImageryScene $scene): View
    {
        return view('staff.intelligence.imagery.scene', [
            'scene' => $scene,
            'projects' => DevelopmentProject::query()->orderBy('name')->get(['id', 'name']),
            'localities' => $this->localities(),
        ]);
    }

    public function updateScene(Request $request, ImageryScene $scene, PublicationDesk $desk, IntelligenceFiles $files): RedirectResponse
    {
        $this->guardEdit($request, $scene);
        $data = $this->sceneData($request);
        $data['redistribution_allowed'] = $request->boolean('redistribution_allowed');
        $scene->update($data);

        if ($request->hasFile('image')) {
            $files->store($scene, $request->user(), $request->file('image'), 'scene');
        }

        if ($scene->isReleased()) {
            $desk->revise($scene, $request->user(), ['redistribution_allowed' => $scene->redistribution_allowed]);
        }

        return back()->with('status', 'Imagery scene updated. Remote imagery was not downloaded.');
    }

    public function submitScene(Request $request, ImageryScene $scene, PublicationDesk $desk): RedirectResponse
    {
        $this->researcher($request);
        $desk->submit($scene, $request->user());

        return back()->with('status', 'Scene submitted for review.');
    }

    public function publishScene(Request $request, ImageryScene $scene, PublicationDesk $desk): RedirectResponse
    {
        $this->editor($request);
        $desk->publish($scene, $request->user());

        return ApprovalsReturn::back($request, 'intelligence', 'Scene published.');
    }

    public function createComparison(): View
    {
        return view('staff.intelligence.imagery.comparison', [
            'comparison' => new ImageryComparison,
            'scenes' => ImageryScene::query()->orderBy('acquired_on')->get(),
            'projects' => DevelopmentProject::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storeComparison(Request $request, PublicationDesk $desk): RedirectResponse
    {
        $this->researcher($request);
        $data = $this->comparisonData($request);
        $comparison = ImageryComparison::query()->create([
            ...$data,
            'reference' => $desk->reference('CP', ImageryComparison::class),
            'slug' => $desk->slug($data['area_label'], ImageryComparison::class),
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $request->user()->id,
        ]);
        $desk->audit($comparison, $request->user(), 'created', null, PublicationStatus::Draft);

        return redirect()->route('staff.imagery.comparisons.show', $comparison)->with('status', 'Comparison draft saved.');
    }

    public function showComparison(ImageryComparison $comparison): View
    {
        $comparison->load(['beforeScene.files', 'afterScene.files', 'project', 'audits.actor']);

        return view('staff.intelligence.imagery.show', ['comparison' => $comparison]);
    }

    public function submitComparison(Request $request, ImageryComparison $comparison, PublicationDesk $desk): RedirectResponse
    {
        $this->researcher($request);
        $desk->submit($comparison, $request->user());

        return back()->with('status', 'Comparison submitted for review.');
    }

    public function publishComparison(Request $request, ImageryComparison $comparison, PublicationDesk $desk): RedirectResponse
    {
        $this->editor($request);
        $comparison->load(['beforeScene', 'afterScene']);
        abort_unless($comparison->beforeScene?->isReleased() && $comparison->afterScene?->isReleased(), 422);

        $desk->publish($comparison, $request->user());

        return ApprovalsReturn::back($request, 'intelligence', 'Comparison published.');
    }

    /**
     * @return array<string, mixed>
     */
    private function sceneData(Request $request): array
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'max:160'],
            'acquired_on' => ['required', 'date'],
            'resolution' => ['required', 'string', 'max:80'],
            'coverage' => ['required', 'string', 'max:160'],
            'cloud_conditions' => ['nullable', 'string', 'max:160'],
            'license_name' => ['required', 'string', 'max:160'],
            'source_url' => ['nullable', 'url', 'max:500'],
            'locality_id' => ['nullable', 'exists:localities,id'],
            'development_project_id' => ['nullable', 'exists:development_projects,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        unset($data['image']);
        $data['county_id'] = empty($data['locality_id']) ? null : Locality::query()->whereKey($data['locality_id'])->value('county_id');

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function comparisonData(Request $request): array
    {
        $data = $request->validate([
            'area_label' => ['required', 'string', 'max:160'],
            'before_scene_id' => ['required', 'exists:imagery_scenes,id'],
            'after_scene_id' => ['required', 'exists:imagery_scenes,id', 'different:before_scene_id'],
            'development_project_id' => ['nullable', 'exists:development_projects,id'],
            'observed_change' => ['required', 'string', 'min:20', 'max:5000'],
            'annotation' => ['nullable', 'string', 'max:5000'],
        ]);

        return $data;
    }
}
