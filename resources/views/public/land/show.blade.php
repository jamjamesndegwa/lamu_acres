<x-public-layout
    :title="$listing->title.' — Lamu Acres'"
    :description="$listing->title.' in '.$listing->locationLabel().'. Published land listing.'"
>
    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <p class="text-sm text-stone-600">
            <a class="text-lamu-800 hover:underline" href="{{ route('land.index') }}">Land in Lamu</a>
            @if ($listing->areaCounty)
                / <a class="text-lamu-800 hover:underline" href="{{ route('land.index', $listing->areaCounty) }}">{{ $listing->areaCounty->name }}</a>
            @endif
            @if ($listing->areaCounty && $listing->areaLocality)
                / <a class="text-lamu-800 hover:underline" href="{{ route('land.index', [$listing->areaCounty, $listing->areaLocality]) }}">{{ $listing->areaLocality->name }}</a>
            @endif
        </p>
        <h1 class="mt-4 text-4xl font-semibold text-lamu-900">{{ $listing->title }}</h1>
        <p class="mt-3 text-stone-700">{{ $listing->locationLabel() }}</p>
        <dl class="mt-8 grid gap-4 rounded-2xl border border-sand-200 bg-white p-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-stone-500">Purpose</dt>
                <dd class="font-medium text-lamu-900">{{ $listing->purpose?->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">Property type</dt>
                <dd class="font-medium text-lamu-900">{{ $listing->property_type?->label() }}</dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">Acreage</dt>
                <dd class="font-medium text-lamu-900">{{ $listing->acreage ? rtrim(rtrim(number_format((float) $listing->acreage, 2), '0'), '.') : 'Not stated' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">Price</dt>
                <dd class="font-medium text-lamu-900">{{ $listing->formattedPrice() ?? 'Price on request' }}</dd>
            </div>
            @if ($listing->pricePerAcre())
                <div>
                    <dt class="text-sm text-stone-500">Price per acre</dt>
                    <dd class="font-medium text-lamu-900">KSh {{ number_format($listing->pricePerAcre()) }}</dd>
                </div>
            @endif
        </dl>
        @if ($listing->summary)
            <div class="prose mt-8 max-w-none text-stone-700">
                <h2 class="text-xl font-semibold text-lamu-900">Summary</h2>
                <p class="mt-3">{{ $listing->summary }}</p>
            </div>
        @endif

        <div class="mt-8 flex flex-wrap gap-3">
            @auth
                @can('save', $listing)
                    @if ($saved)
                        <form method="POST" action="{{ route('land.saved.destroy', $listing) }}" data-confirm="Remove this saved property?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-full border border-sand-200 bg-white px-5 py-3 text-sm font-medium text-lamu-900">Remove saved property</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('land.saved.store', $listing) }}" data-confirm="Save this property?">
                            @csrf
                            <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save property</button>
                        </form>
                    @endif
                    <a href="{{ route('land.enquiries.create', $listing) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-5 py-3 text-sm font-medium text-lamu-900">Send enquiry</a>
                @endcan
            @else
                <a href="{{ route('login') }}" data-turbo-frame="modal" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Login to save or enquire</a>
            @endauth
        </div>

        @can('enquire', $listing)
            <section id="enquire" class="mt-10 rounded-2xl border border-sand-200 bg-white p-5">
                <h2 class="font-semibold text-lamu-900">Send an enquiry</h2>
                <p class="mt-1 text-sm text-stone-600">This question is recorded against your account and this property.</p>
                <div class="mt-4">
                    @include('land.partials.enquiry-form', ['property' => $listing, 'prefix' => ''])
                </div>
            </section>
        @endcan
    </article>
</x-public-layout>
