<x-workspace-layout :heading="$sourcingRequest->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $sourcingRequest->status->label() }} · {{ $sourcingRequest->location_label ?: 'Map centre' }}</p>
    @if ($sourcingRequest->status->value !== 'closed')
        <a href="{{ route('buyer.sourcing.edit', $sourcingRequest) }}" class="mt-4 inline-block text-sm font-medium text-lamu-700">Edit search</a>
    @endif

    <section class="mt-6" aria-labelledby="search-progress">
        <h2 id="search-progress" class="font-semibold text-lamu-900">Progress</h2>
        <dl class="mt-4 grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-sand-200 bg-white p-5">
                <dt class="text-sm text-stone-500">Status</dt>
                <dd class="mt-2 font-semibold text-lamu-900">{{ $sourcingRequest->status->label() }}</dd>
            </div>
            <div class="rounded-2xl border border-sand-200 bg-white p-5">
                <dt class="text-sm text-stone-500">Exact matches</dt>
                <dd class="mt-2 text-2xl font-semibold text-lamu-900">{{ $exactMatches->count() }}</dd>
            </div>
            <div class="rounded-2xl border border-sand-200 bg-white p-5">
                <dt class="text-sm text-stone-500">Near matches</dt>
                <dd class="mt-2 text-2xl font-semibold text-lamu-900">{{ $nearMatches->count() }}</dd>
            </div>
        </dl>
    </section>

    @foreach (['Exact matches' => $exactMatches, 'Near matches' => $nearMatches] as $heading => $rows)
        <section class="mt-8">
            <h2 class="font-semibold text-lamu-900">{{ $heading }}</h2>
            <div class="mt-4">
                <x-workspace.table :columns="['Property', 'Distance', 'Notes']">
                    @forelse ($rows as $match)
                        <tr class="border-b border-sand-200 last:border-b-0">
                            <td class="px-4 py-4 text-sm">
                                <p class="font-medium text-lamu-900">{{ $match->property->title }}</p>
                                <p class="text-xs text-stone-500">{{ $match->property->locationLabel() }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm">{{ $match->distance_km !== null ? $match->distance_km.' km' : '—' }}</td>
                            <td class="px-4 py-4 text-sm text-stone-600">{{ implode(' ', $match->reasons ?? []) ?: 'Meets the recorded criteria.' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-8">
                                <x-workspace.empty title="None in this group" message="Properties are grouped again whenever the search is saved." />
                            </td>
                        </tr>
                    @endforelse
                </x-workspace.table>
            </div>
        </section>
    @endforeach

    <section class="mt-8">
        <h2 class="font-semibold text-lamu-900">History</h2>
        <ol class="mt-4 space-y-3">
            @foreach ($sourcingRequest->activities as $activity)
                <li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                    <p>{{ $activity->summary }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ $activity->created_at->toDayDateTimeString() }}</p>
                </li>
            @endforeach
        </ol>
    </section>
</x-workspace-layout>
