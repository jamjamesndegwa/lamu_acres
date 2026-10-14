<x-workspace-layout heading="My Watchlists">
    <p class="mt-3 max-w-3xl text-sm text-stone-600">Alerts are sent only after a development record is published. A draft is not listed here.</p>
    <form method="POST" action="{{ route('investor.watchlists.store') }}" class="mt-6 grid max-w-xl gap-3">
        @csrf
        <label class="text-sm">Location
            <select name="locality_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                <option value="">None</option>
                @foreach ($localities as $locality)
                    <option value="{{ $locality->id }}">{{ $locality->name }}</option>
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
        <button class="w-fit rounded-full bg-lamu-900 px-5 py-3 text-sm text-white">Save watch</button>
    </form>
    <h2 class="mt-8 font-semibold text-lamu-900">Watched locations</h2>
    <ul class="mt-3 space-y-2 text-sm">
        @forelse ($watches->whereNotNull('locality_id') as $watch)
            <li>{{ $watch->locality?->name }}</li>
        @empty
            <li class="text-stone-600">No location is being watched.</li>
        @endforelse
    </ul>
    <h2 class="mt-8 font-semibold text-lamu-900">Watched projects</h2>
    <ul class="mt-3 space-y-2 text-sm">
        @forelse ($watches->whereNotNull('development_project_id') as $watch)
            <li>{{ $watch->project?->name }} @if ($watch->project) · {{ $watch->project->project_status->label() }}. {{ $watch->project->project_status->statement() }} @endif</li>
        @empty
            <li class="text-stone-600">No watches yet.</li>
        @endforelse
    </ul>
</x-workspace-layout>
