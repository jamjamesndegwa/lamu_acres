@php
    $ranges = [
        'today' => 'Today',
        '7' => '7 days',
        '30' => '30 days',
        'month' => 'This month',
        'last_month' => 'Last month',
        'quarter' => 'This quarter',
        'year' => 'This year',
    ];
@endphp

<form method="get" action="{{ route(request()->route()->getName()) }}" class="mt-6 flex flex-wrap items-end gap-3">
    <div class="flex flex-wrap gap-2" role="group" aria-label="Reporting period">
        @foreach ($ranges as $key => $label)
            <a
                href="{{ route(request()->route()->getName(), ['range' => $key]) }}"
                @class([
                    'rounded-full px-3 py-1.5 text-sm',
                    'bg-lamu-900 text-white' => $window->range === (string) $key,
                    'bg-white text-lamu-900 ring-1 ring-sand-200' => $window->range !== (string) $key,
                ])
                @if ($window->range === (string) $key)
                    aria-current="page"
                @endif
            >{{ $label }}</a>
        @endforeach
    </div>
    <input type="hidden" name="range" value="custom">
    <label class="text-sm text-stone-600">
        From
        <input type="date" name="from" value="{{ $window->range === 'custom' ? $window->from->toDateString() : '' }}" class="mt-1 block rounded-lg border border-sand-200 px-3 py-2">
    </label>
    <label class="text-sm text-stone-600">
        To
        <input type="date" name="to" value="{{ $window->range === 'custom' ? $window->to->toDateString() : '' }}" class="mt-1 block rounded-lg border border-sand-200 px-3 py-2">
    </label>
    <button type="submit" @class([
        'rounded-full px-4 py-2 text-sm font-medium',
        'bg-lamu-900 text-white' => $window->range === 'custom',
        'bg-white text-lamu-900 ring-1 ring-sand-200' => $window->range !== 'custom',
    ])>Custom</button>
</form>
<p class="mt-3 text-sm text-stone-600">{{ $window->label() }}. Completed sales, enquiries, requested locations, commission paid, premium receipts, renewals, and recent activity use this period. Other figures are the current position. Expiring subscriptions look 30 days ahead.</p>
@if ($errors->any())
    <p class="mt-2 text-sm text-red-700">{{ $errors->first() }}</p>
@endif
