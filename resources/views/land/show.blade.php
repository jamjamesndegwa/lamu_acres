<x-public-layout :title="$property->title.' — Lamu Acres'">
    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ $property->reference }}</p>
        <h1 class="mt-2 text-3xl font-semibold text-lamu-900">{{ $property->title }}</h1>
        <p class="mt-2 text-stone-600">{{ $property->locationLabel() ?: 'Lamu' }}</p>
        @if ($property->acreage)
            <p class="mt-1 text-sm text-stone-600">{{ $property->acreage }} acres</p>
        @endif
        @if ($property->summary)
            <p class="mt-6 text-stone-700">{{ $property->summary }}</p>
        @endif

        <div class="mt-8 flex flex-wrap gap-3">
            @auth
                @can('save', $property)
                    @if ($saved)
                        <form method="POST" action="{{ route('land.saved.destroy', $property) }}" data-confirm="Remove this saved property?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-full border border-sand-200 bg-white px-5 py-3 text-sm font-medium text-lamu-900">Remove saved property</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('land.saved.store', $property) }}" data-confirm="Save this property?">
                            @csrf
                            <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save property</button>
                        </form>
                    @endif
                    <a href="{{ route('land.enquiries.create', $property) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-5 py-3 text-sm font-medium text-lamu-900">Send enquiry</a>
                @endcan
            @else
                <a href="{{ route('login') }}" data-turbo-frame="modal" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Login to save or enquire</a>
            @endauth
        </div>

        @can('enquire', $property)
            <section id="enquire" class="mt-10 rounded-2xl border border-sand-200 bg-white p-5">
                <h2 class="font-semibold text-lamu-900">Send an enquiry</h2>
                <p class="mt-1 text-sm text-stone-600">This question is recorded against your account and this property.</p>
                <div class="mt-4">
                    @include('land.partials.enquiry-form', ['property' => $property, 'prefix' => ''])
                </div>
            </section>
        @endcan
    </article>
</x-public-layout>
