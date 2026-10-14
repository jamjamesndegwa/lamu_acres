<x-crud-page title="Listing Rules" heading="Listing Rules">
    <p class="text-sm text-lamu-800/70">Publication categories already in use. Listing lifecycle is explained here and is not a separate settings record.</p>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-sand-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-lamu-900">Publication status</h2>
            <ul class="mt-2 space-y-2 text-sm text-lamu-800/80">
                @foreach ($listingStatuses as $status)
                    @php
                        $next = collect($status->transitions())->map->label()->join(', ');
                    @endphp
                    <li>
                        <span class="font-medium text-lamu-900">{{ $status->label() }}</span>
                        @if ($next === '')
                            — no further transition
                        @else
                            — may move to {{ $next }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="rounded-lg border border-sand-200 bg-white p-4 text-sm text-lamu-800/80">
            <h2 class="text-sm font-semibold text-lamu-900">Availability</h2>
            <ul class="mt-2 space-y-1">
                @foreach ($availability as $state)
                    <li>{{ $state->label() }}</li>
                @endforeach
            </ul>
            <p class="mt-3">A listing appears in the public directory when it is published and available. Price, media, and status changes stay on the property record.</p>
        </div>
    </div>
</x-crud-page>
