<x-public-layout
    :title="$listing['title'].' — Lamu Acres'"
    :description="\Illuminate\Support\Str::limit(trim(strip_tags((string) ($listing['summary'] ?: $listing['description'] ?: $listing['title'].' in '.$listing['location']))), 160)"
>
    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <script type="application/ld+json">{!! json_encode($listing['schema'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

        <p class="text-sm text-stone-600">
            <a class="text-lamu-800 hover:underline" href="{{ route('land.index') }}">Land in Lamu</a>
            @if ($property->areaCounty)
                / <a class="text-lamu-800 hover:underline" href="{{ route('land.index', $property->areaCounty) }}">{{ $property->areaCounty->name }}</a>
            @endif
            @if ($property->areaCounty && $property->areaLocality)
                / <a class="text-lamu-800 hover:underline" href="{{ route('land.index', [$property->areaCounty, $property->areaLocality]) }}">{{ $property->areaLocality->name }}</a>
            @endif
        </p>
        <h1 class="mt-4 text-4xl font-semibold text-lamu-900">{{ $listing['title'] }}</h1>
        <p class="mt-3 text-stone-700">{{ $listing['location'] }}</p>

        <dl class="mt-8 grid gap-4 rounded-2xl border border-sand-200 bg-white p-6 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-stone-500">Availability</dt>
                <dd class="font-medium text-lamu-900">{{ $listing['availability'] }}</dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">Listing status</dt>
                <dd class="mt-1"><x-status-badge :label="$listing['status']" /></dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">Asking price</dt>
                <dd class="font-medium text-lamu-900">{{ $listing['price'] ?? 'Price on request' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">Acreage</dt>
                <dd class="font-medium text-lamu-900">{{ $listing['acreage'] === 'Not stated' ? 'Not stated' : $listing['acreage'].' acres' }}</dd>
            </div>
            @if ($listing['price_per_acre'])
                <div>
                    <dt class="text-sm text-stone-500">Price per acre</dt>
                    <dd class="font-medium text-lamu-900">KSh {{ number_format($listing['price_per_acre']) }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-sm text-stone-500">Purpose</dt>
                <dd class="font-medium text-lamu-900">{{ $listing['purpose'] }}</dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">Property type</dt>
                <dd class="font-medium text-lamu-900">{{ $listing['type'] }}</dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">Road access</dt>
                <dd class="font-medium text-lamu-900">{{ $listing['road'] }}</dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">Public review</dt>
                <dd class="font-medium text-lamu-900">{{ $listing['verification'] }}</dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">Last review</dt>
                <dd class="font-medium text-lamu-900">{{ $listing['reviewed_on'] ?? 'No review date recorded' }}</dd>
            </div>
        </dl>

        <section class="mt-10">
            <h2 class="text-xl font-semibold text-lamu-900">Photos</h2>
            <div class="mt-4">
                <x-property-gallery :photos="$listing['photos']" />
            </div>
        </section>

        <section class="mt-10">
            <h2 class="text-xl font-semibold text-lamu-900">Videos</h2>
            @forelse ($listing['videos'] as $video)
                <p class="mt-3"><a href="{{ $video['url'] }}" class="font-medium text-lamu-800 underline" rel="noopener noreferrer">Watch video</a></p>
            @empty
                <p class="mt-3 text-sm text-stone-600">No video link has been published.</p>
            @endforelse
        </section>

        <section class="mt-10">
            <h2 class="text-xl font-semibold text-lamu-900">Highlights</h2>
            @if ($listing['highlights'] === [])
                <p class="mt-3 text-sm text-stone-600">No highlights have been published.</p>
            @else
                <ul class="mt-3 list-disc space-y-2 pl-5 text-stone-700">
                    @foreach ($listing['highlights'] as $highlight)
                        <li>{{ $highlight }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($listing['description'])
            <section class="mt-10">
                <h2 class="text-xl font-semibold text-lamu-900">Description</h2>
                <p class="mt-3 whitespace-pre-line text-stone-700">{{ $listing['description'] }}</p>
            </section>
        @elseif ($listing['summary'])
            <section class="mt-10">
                <h2 class="text-xl font-semibold text-lamu-900">Summary</h2>
                <p class="mt-3 text-stone-700">{{ $listing['summary'] }}</p>
            </section>
        @endif

        <section class="mt-10">
            <h2 class="text-xl font-semibold text-lamu-900">Amenities</h2>
            <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($listing['amenities'] as $amenity)
                    <div class="rounded-2xl border border-sand-200 bg-white px-4 py-3">
                        <dt class="text-sm text-stone-500">{{ $amenity['name'] }}</dt>
                        <dd class="font-medium text-lamu-900">{{ $amenity['state'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="mt-10">
            <h2 class="text-xl font-semibold text-lamu-900">Documentation checklist</h2>
            <p class="mt-2 text-sm text-stone-600">This is a status summary. Original files are not published on this page.</p>
            <x-document-checklist
                empty="No public document status has been recorded."
                :items="collect($listing['documents'])->map(fn ($document) => ['label' => $document['name'], 'state' => $document['state']])->all()"
            />
        </section>

        <section class="mt-10 rounded-2xl border border-sand-200 bg-white p-6" aria-labelledby="verification-summary">
            <h2 id="verification-summary" class="text-xl font-semibold text-lamu-900">Verification</h2>
            <p class="mt-2 text-stone-700">{{ $passport['level'] }}</p>
            @if ($passport['override'])
                <p class="mt-1 text-sm text-stone-600">{{ $passport['override'] }}</p>
            @endif
            <x-document-checklist
                empty="No public verification summary has been recorded."
                :items="collect($passport['items'])->map(fn ($item) => ['label' => $item['category'], 'state' => $item['outcome']])->all()"
            />
        </section>

        <section class="mt-10">
            <h2 class="text-xl font-semibold text-lamu-900">Approximate area</h2>
            @if ($listing['map'])
                <x-map-viewer :embed="$listing['map']" />
                <p class="mt-2 text-sm text-stone-600">The map shows a general area. It is not a parcel boundary.</p>
            @else
                <p class="mt-3 text-sm text-stone-600">No public map point has been recorded.</p>
            @endif
        </section>

        <section class="mt-10">
            <h2 class="text-xl font-semibold text-lamu-900">Nearby public notes</h2>
            @if ($listing['infrastructure'])
                <p class="mt-3 text-stone-700">{{ $listing['infrastructure'] }}</p>
            @else
                <p class="mt-3 text-sm text-stone-600">No public infrastructure note is recorded.</p>
            @endif
            @if ($listing['nearby'] !== [])
                <ul class="mt-3 list-disc space-y-1 pl-5 text-stone-700">
                    @foreach ($listing['nearby'] as $place)
                        <li>{{ $place }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="mt-10 rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="text-xl font-semibold text-lamu-900">Assigned contact</h2>
            <p class="mt-2 font-medium text-lamu-900">{{ $listing['contact_name'] }}</p>
            @if ($listing['contact_email'])
                <p class="mt-1"><a class="text-lamu-800 underline" href="mailto:{{ $listing['contact_email'] }}">{{ $listing['contact_email'] }}</a></p>
            @else
                <p class="mt-1 text-sm text-stone-600">Use the enquiry form to reach the Lamu Acres desk.</p>
            @endif
        </section>

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
                    <a href="{{ route('land.enquiries.create', $property) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-5 py-3 text-sm font-medium text-lamu-900">Enquire</a>
                    <a href="{{ route('land.visits.create', $property) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-5 py-3 text-sm font-medium text-lamu-900">Book site visit</a>
                    <a href="{{ route('land.documents.create', $property) }}" data-turbo-frame="modal" class="rounded-full border border-lamu-900 px-5 py-3 text-sm font-medium text-lamu-900">Request documents</a>
                @endcan
            @else
                <a href="{{ route('login') }}" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Login to enquire or save</a>
            @endauth
        </div>
    </article>
</x-public-layout>
