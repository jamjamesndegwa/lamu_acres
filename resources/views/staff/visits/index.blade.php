<x-workspace-layout heading="Site visits">
    <div class="mt-4 flex gap-3 text-sm">
        <a href="{{ route('staff.visits.index') }}" class="{{ $calendar ? 'text-stone-600' : 'font-medium text-lamu-900' }}">List</a>
        <a href="{{ route('staff.visits.index', ['view' => 'calendar']) }}" class="{{ $calendar ? 'font-medium text-lamu-900' : 'text-stone-600' }}">Calendar</a>
    </div>

    @if ($calendar)
        <div class="mt-6 space-y-6">
            @php
                $days = $visits->groupBy(function ($visit) {
                    $when = $visit->confirmed_for ?? $visit->requested_for;

                    return $when ? $when->toDateString() : 'unscheduled';
                });
            @endphp
            @forelse ($days as $day => $dayVisits)
                <section>
                    <h2 class="font-semibold text-lamu-900">{{ $day === 'unscheduled' ? 'No date recorded' : \Illuminate\Support\Carbon::parse($day)->toFormattedDateString() }}</h2>
                    <div class="mt-3 space-y-3">
                        @foreach ($dayVisits as $visit)
                            @include('staff.visits._card', ['visit' => $visit, 'agents' => $agents])
                        @endforeach
                    </div>
                </section>
            @empty
                <x-workspace.empty title="No visits" message="Buyer visit requests will appear here." />
            @endforelse
        </div>
    @else
        <div class="mt-6 space-y-4">
            @forelse ($visits as $visit)
                @include('staff.visits._card', ['visit' => $visit, 'agents' => $agents])
            @empty
                <x-workspace.empty title="No visits" message="Buyer visit requests will appear here." />
            @endforelse
        </div>
        <div class="mt-4"><x-workspace.pagination :paginator="$visits" /></div>
    @endif
</x-workspace-layout>
