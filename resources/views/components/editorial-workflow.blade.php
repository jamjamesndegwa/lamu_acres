@props(['status' => null])

@php
    $steps = [
        'draft' => 'Draft',
        'review' => 'Review',
        'approve' => 'Approve',
        'publish' => 'Publish',
        'notify' => 'Notify subscribers',
        'archive' => 'Archive / Revise',
    ];
    $current = match ($status?->value ?? null) {
        'draft' => 'draft',
        'review' => 'review',
        'published' => 'publish',
        'withdrawn' => 'archive',
        default => null,
    };
@endphp

<section class="mt-4 rounded-2xl border border-sand-200 bg-white p-4">
    <p class="text-sm font-medium text-lamu-900">Editorial workflow</p>
    <ol class="mt-3 flex flex-wrap gap-2 text-sm">
        @foreach ($steps as $key => $label)
            <li @class([
                'rounded-full px-3 py-1',
                'bg-lamu-900 text-white' => $current === $key || ($current === 'publish' && $key === 'notify'),
                'bg-sand-50 text-stone-600' => $current !== $key && ! ($current === 'publish' && $key === 'notify'),
            ])>{{ $label }}</li>
        @endforeach
    </ol>
    <p class="mt-3 text-sm text-stone-600">An unpublished researcher draft is not a public fact. Publishing a report notifies subscribers. Withdrawing a record archives it for revision.</p>
</section>
