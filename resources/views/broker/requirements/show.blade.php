<x-workspace-layout :heading="$requirement->reference">
    <p class="mt-3 text-sm text-stone-600">{{ $requirement->status->label() }} · {{ $requirement->location_label }}</p>
    <dl class="mt-6 grid max-w-3xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2">
        <div><dt class="text-sm text-stone-500">Acreage</dt><dd>{{ $requirement->min_acreage ?: '—' }} – {{ $requirement->max_acreage ?: '—' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Maximum budget</dt><dd>{{ $requirement->maximum_budget ? 'KSh '.number_format($requirement->maximum_budget) : 'Not stated' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Intended use</dt><dd>{{ $requirement->intended_use?->label() ?? 'Not stated' }}</dd></div>
        <div><dt class="text-sm text-stone-500">Search radius</dt><dd>{{ $requirement->radius_km ? $requirement->radius_km.' km' : 'Not stated' }}</dd></div>
    </dl>
    <h2 class="mt-8 font-semibold text-lamu-900">Your candidates</h2>
    <ul class="mt-3 space-y-1 text-sm">
        @forelse ($candidates as $candidate)
            <li>{{ $candidate->property?->reference }} · {{ $candidate->property?->title }} · {{ $candidate->band->label() }}</li>
        @empty
            <li class="text-stone-600">You have not submitted a candidate for this search.</li>
        @endforelse
    </ul>
    <h2 class="mt-8 font-semibold text-lamu-900">Submit candidate land</h2>
    @if ($listings->isEmpty())
        <p class="mt-2 text-sm text-stone-600">Publish one of your listings before you can nominate it.</p>
    @else
        <form method="POST" action="{{ route('broker.requirements.propose', $requirement) }}" class="mt-3 max-w-xl space-y-3">
            @csrf
            <label class="block text-sm">Your published listing
                <select name="property_id" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
                    @foreach ($listings as $listing)
                        <option value="{{ $listing->id }}">{{ $listing->reference }} · {{ $listing->title }}</option>
                    @endforeach
                </select>
            </label>
            @error('property_id')
                <p class="text-sm text-red-700">{{ $message }}</p>
            @enderror
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Submit candidate</button>
        </form>
    @endif
</x-workspace-layout>
