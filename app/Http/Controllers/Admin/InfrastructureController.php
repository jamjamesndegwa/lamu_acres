<?php

namespace App\Http\Controllers\Admin;

use App\Enums\InfrastructureKind;
use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\InfrastructureFeature;
use App\Models\Locality;
use App\Support\Geo\Wkt;
use App\Support\Turbo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InfrastructureController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        $term = trim((string) $request->query('q'));
        $kind = $request->query('kind');
        $countyId = $request->query('county');
        $like = '%'.addcslashes($term, '%_\\').'%';

        $features = InfrastructureFeature::query()
            ->with(['county:id,name', 'locality:id,name'])
            ->when(is_string($kind) && InfrastructureKind::tryFrom($kind) instanceof InfrastructureKind, fn (Builder $query) => $query->where('kind', $kind))
            ->when(is_string($countyId) && ctype_digit($countyId), fn (Builder $query) => $query->where('county_id', (int) $countyId))
            ->when($term !== '', function (Builder $query) use ($like): void {
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('source', 'like', $like)
                        ->orWhereHas('locality', fn (Builder $places) => $places->where('name', 'like', $like));
                });
            })
            ->orderBy('name')
            ->limit(200)
            ->get();

        $mapFeatures = [];

        foreach ($features as $feature) {
            $ring = Wkt::latLngRing((string) $feature->boundary_wkt);

            if (! $feature->hasPoint() && $ring === []) {
                continue;
            }

            $mapFeatures[] = [
                'name' => $feature->name,
                'kind' => $feature->kind->label(),
                'lat' => $feature->hasPoint() ? (float) $feature->latitude : null,
                'lng' => $feature->hasPoint() ? (float) $feature->longitude : null,
                'ring' => $ring,
                'boundaryStored' => $ring !== [],
                'note' => $ring !== [] ? 'Verified boundary' : 'Named point. Boundary not stored.',
            ];
        }

        return view('admin.geography.infrastructure', [
            'features' => $features,
            'mapFeatures' => $mapFeatures,
            'counties' => County::query()->orderBy('name')->get(['id', 'name']),
            'term' => $term,
            'kind' => is_string($kind) ? $kind : '',
            'countyId' => is_string($countyId) ? $countyId : '',
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        return view('admin.geography.infrastructure-form', [
            'feature' => new InfrastructureFeature(['is_active' => true, 'kind' => InfrastructureKind::Road]),
            'counties' => County::query()->orderBy('name')->get(['id', 'name']),
            'places' => $this->places(),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);
        InfrastructureFeature::query()->create($this->featureData($request));

        return redirect()->route('admin.geography.infrastructure.index')->with('status', 'Infrastructure reference saved.');
    }

    public function edit(Request $request, InfrastructureFeature $feature): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        return view('admin.geography.infrastructure-form', [
            'feature' => $feature,
            'counties' => County::query()->orderBy('name')->get(['id', 'name']),
            'places' => $this->places(),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function update(Request $request, InfrastructureFeature $feature): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);
        $feature->update($this->featureData($request, $feature));

        return redirect()->route('admin.geography.infrastructure.index')->with('status', 'Infrastructure reference saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function featureData(Request $request, ?InfrastructureFeature $feature = null): array
    {
        $locality = Rule::exists('localities', 'id');

        if (filled($request->input('county_id'))) {
            $locality = $locality->where('county_id', $request->integer('county_id'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(InfrastructureKind::class)],
            'county_id' => ['nullable', 'integer', Rule::exists('counties', 'id')],
            'locality_id' => ['nullable', 'integer', $locality],
            'source' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'boundary_wkt' => ['nullable', 'string', 'max:20000', 'regex:/^(POLYGON|MULTIPOLYGON)\s*\(/i'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $data['kind'] = InfrastructureKind::from($data['kind']);
        $data['slug'] = $feature?->slug ?? Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['county_id'] = $data['county_id'] ?? null;
        $data['locality_id'] = $data['locality_id'] ?? null;
        $data['source'] = trim($data['source']);
        $data['notes'] = filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null;
        $data['latitude'] = $data['latitude'] ?? null;
        $data['longitude'] = $data['longitude'] ?? null;
        $data['boundary_wkt'] = filled($data['boundary_wkt'] ?? null) ? trim((string) $data['boundary_wkt']) : null;
        $this->rejectDuplicateSlug($data['slug'], $feature?->id);

        return $data;
    }

    private function rejectDuplicateSlug(string $slug, ?int $ignoreId): void
    {
        $exists = InfrastructureFeature::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($slug === '' || $exists) {
            throw ValidationException::withMessages([
                'name' => 'An infrastructure record with this name is already recorded.',
            ]);
        }
    }

    private function places()
    {
        return Locality::query()
            ->with('county:id,name')
            ->orderBy('name')
            ->get(['id', 'county_id', 'name', 'kind']);
    }
}
