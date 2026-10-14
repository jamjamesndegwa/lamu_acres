<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MapLayerKind;
use App\Http\Controllers\Controller;
use App\Models\County;
use App\Models\MapLayer;
use App\Services\Geography\MapLayerCatalog;
use App\Support\Turbo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MapLayerController extends Controller
{
    public function index(Request $request, MapLayerCatalog $catalog): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        $term = trim((string) $request->query('q'));
        $kind = $request->query('kind');
        $layers = MapLayer::query()
            ->with('county:id,name')
            ->when(is_string($kind) && MapLayerKind::tryFrom($kind) instanceof MapLayerKind, fn (Builder $query) => $query->where('kind', $kind))
            ->orderBy('name')
            ->get();

        $previews = $layers->mapWithKeys(fn (MapLayer $layer): array => [$layer->id => $catalog->preview($layer)]);
        $needle = Str::lower($term);

        if ($needle !== '') {
            $layers = $layers->filter(function (MapLayer $layer) use ($needle, $previews): bool {
                $preview = $previews[$layer->id];

                return str_contains(Str::lower($layer->name), $needle)
                    || str_contains(Str::lower((string) $layer->source), $needle)
                    || collect($preview['features'])->contains(fn (array $feature): bool => str_contains(Str::lower($feature['name']), $needle))
                    || collect($preview['missing'])->contains(fn (array $item): bool => str_contains(Str::lower($item['name']), $needle));
            })->values();
        }

        $features = [];
        $gaps = [];

        foreach ($layers as $layer) {
            $preview = $previews[$layer->id];
            $layer->setAttribute('drawn_count', count($preview['features']));
            $layer->setAttribute('missing_count', count($preview['missing']));

            if (! $layer->is_visible) {
                continue;
            }

            $features = array_merge($features, $preview['features']);

            if ($preview['missing'] === []) {
                continue;
            }

            $gaps[] = [
                'layer' => $layer->name,
                'items' => array_slice($preview['missing'], 0, 12),
                'more' => max(0, count($preview['missing']) - 12),
            ];
        }

        return view('admin.geography.layers', [
            'layers' => $layers,
            'features' => $features,
            'gaps' => $gaps,
            'term' => $term,
            'kind' => is_string($kind) ? $kind : '',
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        return view('admin.geography.layer-form', [
            'layer' => new MapLayer(['is_visible' => true, 'kind' => MapLayerKind::Custom]),
            'counties' => County::query()->orderBy('name')->get(['id', 'name']),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);
        MapLayer::query()->create($this->layerData($request));

        return redirect()->route('admin.geography.layers.index')->with('status', 'Map layer saved.');
    }

    public function edit(Request $request, MapLayer $layer): View
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);

        return view('admin.geography.layer-form', [
            'layer' => $layer,
            'counties' => County::query()->orderBy('name')->get(['id', 'name']),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function update(Request $request, MapLayer $layer): RedirectResponse
    {
        abort_unless($request->user()?->hasPermission('admin.geography'), 403);
        $layer->update($this->layerData($request, $layer));

        return redirect()->route('admin.geography.layers.index')->with('status', 'Map layer saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function layerData(Request $request, ?MapLayer $layer = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(MapLayerKind::class)],
            'source' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'county_id' => ['nullable', 'integer', Rule::exists('counties', 'id')],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'boundary_wkt' => ['nullable', 'string', 'max:20000', 'regex:/^(POLYGON|MULTIPOLYGON)\s*\(/i'],
            'is_visible' => ['sometimes', 'boolean'],
        ]);

        $kind = MapLayerKind::from($data['kind']);
        $data['kind'] = $kind;
        $data['slug'] = $layer?->slug ?? Str::slug($data['name']);
        $data['is_visible'] = $request->boolean('is_visible');
        $data['county_id'] = $data['county_id'] ?? null;
        $data['description'] = filled($data['description'] ?? null) ? trim((string) $data['description']) : null;
        $data['source'] = trim($data['source']);

        if ($kind === MapLayerKind::Custom) {
            $data['latitude'] = $data['latitude'] ?? null;
            $data['longitude'] = $data['longitude'] ?? null;
            $data['boundary_wkt'] = filled($data['boundary_wkt'] ?? null) ? trim((string) $data['boundary_wkt']) : null;
        } else {
            $data['latitude'] = null;
            $data['longitude'] = null;
            $data['boundary_wkt'] = null;
        }

        $this->rejectDuplicateSlug($data['slug'], $layer?->id);

        return $data;
    }

    private function rejectDuplicateSlug(string $slug, ?int $ignoreId): void
    {
        $exists = MapLayer::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($slug === '' || $exists) {
            throw ValidationException::withMessages([
                'name' => 'A map layer with this name is already recorded.',
            ]);
        }
    }
}
