<x-workspace-layout heading="My Watchlist">
    @if (session('status'))
        <p class="mt-3 rounded-lg border border-lamu-100 bg-lamu-50 px-4 py-3 text-sm text-lamu-900">{{ session('status') }}</p>
    @endif
    <p class="mt-3 max-w-3xl text-sm text-stone-600">Follow locations or published developments to receive in-app alerts and optional email when matching research is released. County-wide records are never treated as a precise local match unless they are linked to the same locality.</p>

    <form method="POST" action="{{ route('investor.watchlists.store') }}" class="mt-6 grid max-w-xl gap-3 rounded-2xl border border-sand-200 bg-white p-4">
        @csrf
        <label class="text-sm">Location
            <select name="locality_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                <option value="">None</option>
                @foreach ($localities as $locality)
                    <option value="{{ $locality->id }}">{{ $locality->name }}@if ($locality->county) · {{ $locality->county->name }}@endif</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm">Published project
            <select name="development_project_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                <option value="">None</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="w-fit rounded-full bg-lamu-900 px-5 py-3 text-sm text-white">Add to watchlist</button>
    </form>

    <h2 class="mt-8 font-semibold text-lamu-900">Watched locations</h2>
    <ul class="mt-3 space-y-3">
        @forelse ($watches->whereNotNull('locality_id') as $watch)
            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sand-200 bg-white px-4 py-3 text-sm">
                <div>
                    <p class="font-medium text-lamu-900">{{ $watch->locality?->name }}</p>
                    <a href="{{ route('investor.opportunities.index', [$watch->locality?->county, $watch->locality]) }}" class="text-xs text-lamu-800 hover:underline">Browse opportunities in this location</a>
                </div>
                <form method="POST" action="{{ route('investor.watchlists.destroy', $watch) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-full border border-sand-200 px-3 py-1.5 text-xs font-medium text-lamu-800">Remove</button>
                </form>
            </li>
        @empty
            <li class="text-sm text-stone-600">No locations are being watched.</li>
        @endforelse
    </ul>

    <h2 class="mt-8 font-semibold text-lamu-900">Watched projects</h2>
    <ul class="mt-3 space-y-3">
        @forelse ($watches->whereNotNull('development_project_id') as $watch)
            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sand-200 bg-white px-4 py-3 text-sm">
                <div>
                    <p class="font-medium text-lamu-900">{{ $watch->project?->name }}</p>
                    @if ($watch->project)
                        <a href="{{ route('investor.projects.show', $watch->project) }}" class="text-xs text-lamu-800 hover:underline">Open project · {{ $watch->project->project_status->label() }}</a>
                    @endif
                </div>
                <form method="POST" action="{{ route('investor.watchlists.destroy', $watch) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-full border border-sand-200 px-3 py-1.5 text-xs font-medium text-lamu-800">Remove</button>
                </form>
            </li>
        @empty
            <li class="text-sm text-stone-600">No projects are being watched.</li>
        @endforelse
    </ul>
</x-workspace-layout>
