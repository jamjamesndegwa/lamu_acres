<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LocalityKind;
use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\Locality;
use App\Services\Geography\LocationDirectory;
use App\Support\Geo\Wkt;
use App\Support\Turbo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GeographyController extends Controller
{
    public function index(Request $request, LocationDirectory $directory): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        $term = trim((string) $request->query('q'));

        return view('admin.geography.index', [
            'counties' => $this->countyDirectory($term),
            'matches' => $term === '' ? collect() : $directory->search($term),
            'term' => $term,
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

        $term = trim((string) $request->query('q'));
        $kind = $request->query('kind');
        $localities = $county->localities()
            ->with(['parent:id,name,kind', 'aliases'])
            ->withCount(['properties as listings_count' => fn (Builder $query) => $query->publiclyListed()])
            ->orderBy('name')
            ->get();

        $visible = $localities
            ->when(is_string($kind) && LocalityKind::tryFrom($kind) instanceof LocalityKind, function (Collection $rows) use ($kind): Collection {
                $selected = LocalityKind::from($kind);

                return $rows->filter(fn (Locality $locality): bool => $locality->kind === $selected)->values();
            })
            ->when($term !== '', function (Collection $rows) use ($term): Collection {
                $needle = Str::lower($term);

                return $rows->filter(function (Locality $locality) use ($needle): bool {
                    return str_contains(Str::lower($locality->name), $needle)
                        || $locality->aliases->contains(fn ($alias): bool => str_contains(Str::lower($alias->name), $needle));
                })->values();
            });

        return view('admin.geography.county', [
            'county' => $county,
            'localities' => $visible,
            'places' => $localities,
            'term' => $term,
            'kind' => is_string($kind) ? $kind : '',
            'mapFeatures' => $this->mapFeatures($county, $localities),
        ]);
    }

    public function createLocality(Request $request, County $county): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        return view('admin.geography.locality-form', [
            'county' => $county,
            'locality' => new Locality(['county_id' => $county->id, 'is_active' => true, 'kind' => LocalityKind::Place]),
            'parents' => $county->localities()->orderBy('name')->get(['id', 'name', 'kind']),
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
            'parents' => $locality->county->localities()->whereKeyNot($locality->id)->orderBy('name')->get(['id', 'name', 'kind']),
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
        $slug = Str::slug($data['name']);

        if ($slug === '' || $locality->aliases()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'name' => 'That alias is already recorded for this place.',
            ]);
        }

        $locality->aliases()->create([
            'name' => $data['name'],
            'slug' => $slug,
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

        $data['slug'] = $county?->slug ?? Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['boundary_wkt'] = filled($data['boundary_wkt'] ?? null) ? trim((string) $data['boundary_wkt']) : null;
        $this->rejectDuplicateSlug(County::query(), $data['slug'], $county?->id, 'name', 'A county with this name is already recorded.');

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

        $data['slug'] = $locality?->slug ?? Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $data['boundary_wkt'] = filled($data['boundary_wkt'] ?? null) ? trim((string) $data['boundary_wkt']) : null;
        $data['parent_id'] = $data['parent_id'] ?? null;
        $this->rejectDuplicateSlug(
            Locality::query()->where('county_id', $county->id),
            $data['slug'],
            $locality?->id,
            'name',
            'A place with this name is already recorded in this county.',
        );
        $this->rejectParentCycle($county, isset($data['parent_id']) ? (int) $data['parent_id'] : null, $locality);

        return $data;
    }

    private function countyDirectory(string $term)
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return County::query()
            ->withCount([
                'localities',
                'properties as listings_count' => fn (Builder $query) => $query->publiclyListed(),
            ])
            ->when($term !== '', function (Builder $query) use ($like): void {
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhereHas('localities', function (Builder $places) use ($like): void {
                            $places->where('name', 'like', $like)
                                ->orWhereHas('aliases', fn (Builder $aliases) => $aliases->where('name', 'like', $like));
                        });
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    /**
     * @param  Collection<int, Locality>  $localities
     * @return array<int, array<string, mixed>>
     */
    private function mapFeatures(County $county, Collection $localities): array
    {
        $features = [];

        $countyRing = Wkt::latLngRing((string) $county->boundary_wkt);
        if ($county->hasPoint() || $countyRing !== []) {
            $features[] = [
                'name' => $county->name,
                'kind' => 'County',
                'lat' => $county->hasPoint() ? (float) $county->latitude : null,
                'lng' => $county->hasPoint() ? (float) $county->longitude : null,
                'ring' => $countyRing,
                'boundaryStored' => $county->hasBoundary(),
            ];
        }

        foreach ($localities as $locality) {
            $ring = Wkt::latLngRing((string) $locality->boundary_wkt);

            if (! $locality->hasPoint() && $ring === []) {
                continue;
            }

            $features[] = [
                'name' => $locality->name,
                'kind' => $locality->kind->label(),
                'lat' => $locality->hasPoint() ? (float) $locality->latitude : null,
                'lng' => $locality->hasPoint() ? (float) $locality->longitude : null,
                'ring' => $ring,
                'boundaryStored' => $locality->hasBoundary(),
            ];
        }

        return $features;
    }

    private function rejectDuplicateSlug(Builder $query, string $slug, ?int $ignoreId, string $field, string $message): void
    {
        if ($slug === '' || $query->where('slug', $slug)->when($ignoreId, fn (Builder $inner) => $inner->whereKeyNot($ignoreId))->exists()) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function rejectParentCycle(County $county, ?int $parentId, ?Locality $locality): void
    {
        if ($parentId === null || $locality === null) {
            return;
        }

        $parent = Locality::query()->where('county_id', $county->id)->find($parentId);
        $guard = 0;

        while ($parent !== null && $guard < 25) {
            if ($parent->is($locality)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A place cannot be placed inside itself.',
                ]);
            }

            $parent = $parent->parent;
            $guard++;
        }
    }
}
