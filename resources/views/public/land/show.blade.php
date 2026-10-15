<x-public-layout
    :title="$listing['title'].' — Lamu Acres'"
    :description="\Illuminate\Support\Str::limit(trim(strip_tags((string) ($listing['summary'] ?: $listing['description'] ?: $listing['title'].' in '.$listing['location']))), 160)"
>
    <article class="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:px-8 lg:py-10">
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
        <h1 class="home-serif mt-4 max-w-4xl text-3xl font-semibold leading-tight text-lamu-900 sm:text-4xl lg:text-5xl">{{ $listing['title'] }}</h1>
        <div class="mt-4 flex flex-wrap items-center gap-3 text-sm text-stone-600"><span>{{ $listing['location'] }}</span><span aria-hidden="true">·</span><x-status-badge :label="$listing['purpose']" /><x-status-badge :label="$listing['availability']" /></div>

        <section id="listing-photos" aria-label="Property photographs" class="mt-7">
            @if ($listing['photos'] !== [])
                <div class="grid gap-3 lg:grid-cols-3">
                    <button type="button" data-dialog-open="listing-photo-gallery" aria-label="Open all property photographs" class="group relative overflow-hidden rounded-2xl bg-sand-200 text-left focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-lamu-800 lg:col-span-2">
                        <img src="{{ $listing['photos'][0]['url'] }}" alt="{{ $listing['photos'][0]['alt'] }}" fetchpriority="high" class="h-72 w-full object-cover transition duration-300 group-hover:scale-[1.02] sm:h-96 lg:h-[460px]">
                        <span class="absolute bottom-4 left-4 rounded-full bg-white px-4 py-2 text-sm font-medium text-lamu-900 shadow-sm">View all {{ count($listing['photos']) }} photos</span>
                    </button>
                    <div class="hidden gap-3 lg:grid {{ count($listing['photos']) > 2 ? 'grid-rows-2' : 'grid-rows-1' }}">
                        @forelse (array_slice($listing['photos'], 1, 2) as $photo)
                            <button type="button" data-dialog-open="listing-photo-gallery" aria-label="Open property photo gallery" class="overflow-hidden rounded-2xl bg-sand-200 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-lamu-800">
                                <img src="{{ $photo['url'] }}" alt="{{ $photo['alt'] }}" loading="lazy" class="h-full max-h-[460px] min-h-0 w-full object-cover">
                            </button>
                        @empty
                            <div class="flex flex-col justify-between rounded-2xl bg-lamu-900 p-7 text-white">
                                <span class="text-xs font-medium uppercase tracking-widest text-sand-200">{{ $listing['location'] }}</span>
                                <div><p class="home-serif text-3xl leading-tight">A closer look at your next opportunity.</p><p class="mt-4 text-sm leading-relaxed text-sand-200">Explore the details, review the published checks and arrange a visit with the Lamu Acres desk.</p></div>
                                <a href="#enquire" class="mt-6 text-sm font-semibold underline underline-offset-4">Discuss this property &rarr;</a>
                            </div>
                        @endforelse
                    </div>
                </div>
                <dialog id="listing-photo-gallery" aria-labelledby="listing-gallery-heading" class="m-auto max-h-[90dvh] w-[94vw] max-w-6xl overflow-y-auto rounded-2xl bg-sand-50 p-4 backdrop:bg-black/70 sm:p-6">
                    <div class="mb-4 flex items-center justify-between gap-4"><h2 id="listing-gallery-heading" class="home-serif text-2xl font-semibold text-lamu-900">Property photographs</h2><button type="button" data-dialog-close class="rounded-full border border-sand-200 bg-white px-4 py-2 text-sm font-medium" aria-label="Close photo gallery">Close</button></div>
                    <x-property-gallery :photos="$listing['photos']" />
                </dialog>
            @else
                <div class="overflow-hidden rounded-2xl bg-sand-200"><img src="{{ asset('images/default_land_image.jpg') }}" alt="Lamu coastal landscape" class="h-72 w-full object-cover sm:h-96"><p class="bg-white p-4 text-sm text-stone-600">Property photographs have not been published yet.</p></div>
            @endif
        </section>
        <nav aria-label="On this property page" class="mt-6 flex gap-6 overflow-x-auto border-b border-sand-200 py-4 text-sm font-medium text-lamu-900">
            <a href="#property-overview" class="shrink-0 hover:underline">Overview</a>
            <a href="#property-details" class="shrink-0 hover:underline">Property details</a>
            <a href="#verification-summary" class="shrink-0 hover:underline">Verification</a>
            <a href="#property-location" class="shrink-0 hover:underline">Location</a>
            <a href="#enquire" class="shrink-0 hover:underline">Enquire</a>
        </nav>
        <div class="mt-8 grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_340px] xl:gap-12">
            <div class="min-w-0">
                <section id="property-overview" class="scroll-mt-24 rounded-2xl border border-sand-200 bg-white p-5 sm:p-7">
                    <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">The opportunity</p>
                    <h2 class="home-serif mt-2 text-3xl font-semibold text-lamu-900">Land in {{ $listing['location'] }}</h2>
                    <p class="mt-3 leading-relaxed text-stone-600">{{ $listing['summary'] ?: 'Explore the property details and published checks below, then speak with the Lamu Acres desk to arrange your next step.' }}</p>
                    <dl class="mt-5 grid grid-cols-3 divide-x divide-sand-200 border-t border-sand-200 pt-5">
                        <div class="pr-3"><dt class="text-xs text-stone-500">Area offered</dt><dd class="mt-1 font-semibold text-lamu-900">{{ $listing['acreage'] }}{{ $listing['acreage'] !== 'Not stated' ? ' acres' : '' }}</dd></div>
                        <div class="px-3"><dt class="text-xs text-stone-500">Purpose</dt><dd class="mt-1 font-semibold text-lamu-900">{{ $listing['purpose'] }}</dd></div>
                        <div class="pl-3"><dt class="text-xs text-stone-500">Tenure</dt><dd class="mt-1 font-semibold text-lamu-900">{{ $listing['tenure'] ?: 'On request' }}</dd></div>
                    </dl>
                </section>
                <h2 id="property-details" class="home-serif mt-8 scroll-mt-24 text-2xl font-semibold text-lamu-900">Property details</h2>
        <dl class="mt-4 grid gap-x-8 gap-y-5 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2 sm:p-7">
            <div>
                <dt class="text-sm text-stone-500">Availability</dt>
                <dd class="font-medium text-lamu-900">{{ $listing['availability'] }}</dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">Listing status</dt>
                <dd class="mt-1"><x-status-badge :label="$listing['status']" /></dd>
            </div>
            <div>
                <dt class="text-sm text-stone-500">{{ $listing['price_label'] }}</dt>
                <dd class="font-medium text-lamu-900">{{ $listing['price'] ?? ($listing['is_lease'] ? 'Rent on request' : 'Price on request') }}</dd>
                @if ($listing['price_basis'] || $listing['price_negotiable'])
                    <dd class="text-sm text-stone-600">{{ collect([$listing['price_basis'], $listing['price_negotiable'] ? 'Negotiable' : null])->filter()->implode(' · ') }}</dd>
                @endif
            </div>
            <div>
                <dt class="text-sm text-stone-500">{{ $listing['is_lease'] ? 'Area offered for lease' : 'Area offered' }}</dt>
                <dd class="font-medium text-lamu-900">{{ $listing['acreage'] === 'Not stated' ? 'Not stated' : $listing['acreage'].' acres' }}</dd>
                @if ($listing['minimum_acreage'])
                    <dd class="text-sm text-stone-600">Portions from {{ $listing['minimum_acreage'] }} acres considered</dd>
                @endif
            </div>
            @if ($listing['lease_term'])
                <div>
                    <dt class="text-sm text-stone-500">Lease term</dt>
                    <dd class="font-medium text-lamu-900">{{ $listing['lease_term'] }}</dd>
                </div>
            @endif
            @if ($listing['available_from'])
                <div>
                    <dt class="text-sm text-stone-500">Available from</dt>
                    <dd class="font-medium text-lamu-900">{{ $listing['available_from'] }}</dd>
                </div>
            @endif
            @if ($listing['tenure'])
                <div>
                    <dt class="text-sm text-stone-500">Tenure</dt>
                    <dd class="font-medium text-lamu-900">{{ $listing['tenure'] }}</dd>
                </div>
            @endif
            @if ($listing['landmark'])
                <div>
                    <dt class="text-sm text-stone-500">Nearest landmark</dt>
                    <dd class="font-medium text-lamu-900">{{ $listing['landmark'] }}</dd>
                </div>
            @endif
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
            <h2 class="home-serif text-2xl font-semibold text-lamu-900">Videos</h2>
            @forelse ($listing['videos'] as $video)
                @if ($video['uploaded'] ?? false)
                    <video controls playsinline preload="metadata" src="{{ $video['url'] }}" aria-label="{{ $video['name'] }}" class="mt-3 max-h-[70vh] w-full rounded-2xl bg-stone-900">
                        Your browser does not support this video. <a href="{{ $video['url'] }}">Open video</a>
                    </video>
                @else
                <p class="mt-3"><a href="{{ $video['url'] }}" class="font-medium text-lamu-800 underline" rel="noopener noreferrer">Watch video</a></p>
                @endif
            @empty
                <p class="mt-3 text-sm text-stone-600">No video has been published.</p>
            @endforelse
        </section>

        <section class="mt-10">
            <h2 class="home-serif text-2xl font-semibold text-lamu-900">Highlights</h2>
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
                <h2 class="home-serif text-2xl font-semibold text-lamu-900">Description</h2>
                <p class="mt-3 whitespace-pre-line text-stone-700">{{ $listing['description'] }}</p>
            </section>
        @elseif ($listing['summary'])
            <section class="mt-10">
                <h2 class="home-serif text-2xl font-semibold text-lamu-900">Summary</h2>
                <p class="mt-3 text-stone-700">{{ $listing['summary'] }}</p>
            </section>
        @endif

        @if ($listing['terms'] !== [])
            <section class="mt-10">
                <h2 class="home-serif text-2xl font-semibold text-lamu-900">{{ $listing['is_lease'] ? 'Lease terms and land details' : 'Sale terms and land details' }}</h2>
                <p class="mt-2 text-sm text-stone-600">As declared by the landowner. Confirm all terms with the Lamu Acres desk before relying on them.</p>
                <dl class="mt-4 space-y-3">
                    @foreach ($listing['terms'] as $label => $value)
                        <div class="rounded-2xl border border-sand-200 bg-white px-4 py-3">
                            <dt class="text-sm text-stone-500">{{ $label }}</dt>
                            <dd class="whitespace-pre-line text-stone-800">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        @endif

        <section class="mt-10">
            <h2 class="home-serif text-2xl font-semibold text-lamu-900">Amenities</h2>
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
            <h2 class="home-serif text-2xl font-semibold text-lamu-900">Documentation checklist</h2>
            <p class="mt-2 text-sm text-stone-600">This is a status summary. Original files are not published on this page.</p>
            <x-document-checklist
                empty="No public document status has been recorded."
                :items="collect($listing['documents'])->map(fn ($document) => ['label' => $document['name'], 'state' => $document['state']])->all()"
            />
        </section>

        <section class="mt-10 rounded-2xl border border-sand-200 bg-white p-6" aria-labelledby="verification-summary">
            <h2 id="verification-summary" class="home-serif text-2xl font-semibold text-lamu-900">Verification</h2>
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
            <h2 id="property-location" class="home-serif scroll-mt-24 text-2xl font-semibold text-lamu-900">Approximate area</h2>
            @if ($listing['map'])
                <x-map-viewer :embed="$listing['map']" />
                <p class="mt-2 text-sm text-stone-600">The map shows a general area. It is not a parcel boundary.</p>
            @else
                <p class="mt-3 text-sm text-stone-600">No public map point has been recorded.</p>
            @endif
        </section>

        <section class="mt-10">
            <h2 class="home-serif text-2xl font-semibold text-lamu-900">Nearby public notes</h2>
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

            </div>
            <aside id="enquire" aria-labelledby="enquiry-heading" class="scroll-mt-24 rounded-2xl border border-sand-200 bg-white p-6 shadow-sm lg:sticky lg:top-24">
                <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">{{ $listing['price_label'] }}</p>
                <h2 id="enquiry-heading" class="home-serif mt-2 text-3xl font-semibold text-lamu-900">{{ $listing['price'] ?? ($listing['is_lease'] ? 'Rent on request' : 'Price on request') }}</h2>
                <p class="mt-2 text-sm text-stone-600">{{ collect([$listing['price_basis'], $listing['price_negotiable'] ? 'Negotiable' : null])->filter()->implode(' · ') }}</p>
                <div class="mt-5 rounded-xl bg-sand-50 p-4"><p class="font-medium text-lamu-900">Interested in this property?</p><p class="mt-2 text-sm leading-relaxed text-stone-600">Speak with the Lamu Acres desk for more information, document access or a guided site visit.</p></div>
        <div id="save-property" class="mt-5 grid gap-3 scroll-mt-24 [&_a]:text-center [&_button]:w-full">
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
        <section class="mt-6 border-t border-sand-200 pt-5">
            <h2 class="text-base font-semibold text-lamu-900">Assigned contact</h2>
            <p class="mt-2 font-medium text-lamu-900">{{ $listing['contact_name'] }}</p>
            @if ($listing['contact_email'])
                <p class="mt-1"><a class="text-lamu-800 underline" href="mailto:{{ $listing['contact_email'] }}">{{ $listing['contact_email'] }}</a></p>
            @else
                <p class="mt-1 text-sm text-stone-600">Use the enquiry form to reach the Lamu Acres desk.</p>
            @endif
        </section>

                <div class="mt-5 border-t border-sand-200 pt-4 text-sm"><p class="font-medium text-lamu-900">{{ $listing['verification'] }}</p><p class="mt-1 text-stone-500">Last review: {{ $listing['reviewed_on'] ?? 'Not recorded' }}</p><a href="#verification-summary" class="mt-2 inline-block font-medium text-lamu-800 underline">See published verification checks</a></div>
            </aside>
        </div>
    </article>
</x-public-layout>
