<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LocalityKind;
use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Locality;
use App\Services\Geography\LocationDirectory;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GeographyController extends Controller
{
    public function index(Request $request, LocationDirectory $directory): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        return view('admin.geography.index', [
            'counties' => County::query()->withCount('localities')->orderBy('name')->paginate(20),
            'matches' => $request->filled('q') ? $directory->search((string) $request->query('q')) : collect(),
        ]);
    }

    public function editCounty(Request $request, County $county): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        return view('admin.geography.county-form', [
            'county' => $county,
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function updateCounty(Request $request, County $county): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);
        $county->update($this->countyData($request, $county));

        return redirect()->route('admin.geography.index')->with('status', 'County saved.');
    }

    public function storeCounty(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);
        County::query()->create($this->countyData($request));

        return redirect()->route('admin.geography.index')->with('status', 'County saved.');
    }

    public function createCounty(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        return view('admin.geography.county-form', [
            'county' => new County(['is_active' => true]),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function showCounty(Request $request, County $county): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        return view('admin.geography.county', [
            'county' => $county,
            'localities' => $county->localities()->with(['parent:id,name', 'aliases'])->orderBy('name')->paginate(30),
        ]);
    }

    public function createLocality(Request $request, County $county): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        return view('admin.geography.locality-form', [
            'county' => $county,
            'locality' => new Locality(['county_id' => $county->id, 'is_active' => true, 'kind' => LocalityKind::Place]),
            'parents' => $county->localities()->orderBy('name')->get(['id', 'name']),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function storeLocality(Request $request, County $county): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);
        $county->localities()->create($this->localityData($request, $county));

        return redirect()->route('admin.geography.counties.show', $county)->with('status', 'Locality saved.');
    }

    public function editLocality(Request $request, Locality $locality): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        return view('admin.geography.locality-form', [
            'county' => $locality->county,
            'locality' => $locality,
            'parents' => $locality->county->localities()->whereKeyNot($locality->id)->orderBy('name')->get(['id', 'name']),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function updateLocality(Request $request, Locality $locality): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);
        $locality->update($this->localityData($request, $locality->county, $locality));

        return redirect()->route('admin.geography.counties.show', $locality->county)->with('status', 'Locality saved.');
    }

    public function storeAlias(Request $request, Locality $locality): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $locality->aliases()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
        ]);

        return back()->with('status', 'Alias saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function countyData(Request $request, ?County $county = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:8', Rule::unique('counties', 'code')->ignore($county)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'boundary_wkt' => ['nullable', 'string', 'max:20000', 'regex:/^(POLYGON|MULTIPOLYGON)\s*\(/i'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = $county->slug ?? Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['boundary_wkt'] = $data['boundary_wkt'] ?? null;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function localityData(Request $request, County $county, ?Locality $locality = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(LocalityKind::class)],
            'parent_id' => ['nullable', 'integer', Rule::exists('localities', 'id')->where('county_id', $county->id)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'boundary_wkt' => ['nullable', 'string', 'max:20000', 'regex:/^(POLYGON|MULTIPOLYGON)\s*\(/i'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['slug'] = $locality->slug ?? Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['boundary_wkt'] = $data['boundary_wkt'] ?? null;

        return $data;
    }
}
