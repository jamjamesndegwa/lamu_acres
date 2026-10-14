<x-workspace-layout :heading="$listing->title">
    <p class="mt-3 text-sm text-stone-600">{{ $listing->reference }} · {{ $listing->status->label() }} · submitted by {{ $listing->submitter?->name }}@if ($listing->submitter?->email) · {{ $listing->submitter->email }}@endif</p>
    <p class="mt-2 text-sm"><a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.properties.show', $listing) }}">Open property workspace</a></p>
    <dl class="mt-6 grid max-w-3xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2">
        <div><dt class="text-sm text-stone-500">Seller</dt><dd>{{ $listing->seller_name }}</dd></div>
        <div><dt class="text-sm text-stone-500">Contact</dt><dd>{{ $listing->seller_contact }}</dd></div>
        <div><dt class="text-sm text-stone-500">Relationship</dt><dd>{{ $listing->marketing_role?->label() }}</dd></div>
        <div><dt class="text-sm text-stone-500">Parcel</dt><dd>{{ $listing->parcel_identifier ?: 'Not supplied' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-sm text-stone-500">Location</dt><dd>{{ $listing->locationLabel() }} · {{ $listing->acreage }} acres</dd></div>
    </dl>
    @if ($matches->isNotEmpty())
        <h2 class="mt-8 font-semibold text-lamu-900">Possible duplicates</h2>
        <ul class="mt-3 space-y-1 text-sm">
            @foreach ($matches as $match)
                <li>
                    @if ($match->submitted_by)
                        <a class="text-lamu-800 hover:underline" href="{{ route('staff.listings.show', $match) }}">{{ $match->reference }} · {{ $match->title }}</a>
                    @else
                        {{ $match->reference }} · {{ $match->title }}
                    @endif
                    · {{ $match->status->label() }}
                </li>
            @endforeach
        </ul>
    @endif
    <h2 class="mt-8 font-semibold text-lamu-900">Files</h2>
    <ul class="mt-3 text-sm">
        @foreach ($listing->media as $file)
            <li>
                <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.listings.media', [$listing, $file]) }}">{{ $file->kind === 'authority' ? 'Marketing authority' : 'Photograph' }}: {{ $file->original_name }}</a>
            </li>
        @endforeach
    </ul>
    <h2 class="mt-8 font-semibold text-lamu-900">Information requests</h2>
    <div class="mt-3 max-w-3xl space-y-3">
        @forelse ($listing->informationRequests as $item)
            <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                <p>{{ $item->question }}</p>
                @if ($item->response)
                    <p class="mt-2 text-stone-600">Broker reply: {{ $item->response }}</p>
                @else
                    <p class="mt-2 text-stone-500">Waiting for a reply.</p>
                @endif
            </article>
        @empty
            <p class="text-sm text-stone-600">No questions have been sent.</p>
        @endforelse
    </div>
    <h2 class="mt-8 font-semibold text-lamu-900">Ask for information</h2>
    <form method="POST" action="{{ route('staff.listings.request', $listing) }}" class="mt-3 max-w-3xl space-y-2">
        @csrf
        <textarea name="question" rows="3" required class="w-full rounded-lg border border-sand-200 px-3 py-2"></textarea>
        <button type="submit" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Send question</button>
    </form>
    <h2 class="mt-8 font-semibold text-lamu-900">Verification blockers</h2>
    @if ($blockers === [])
        <p class="mt-2 text-sm text-stone-600">No blocking verification issues.</p>
    @else
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-stone-700">
            @foreach ($blockers as $blocker)
                <li>{{ $blocker }}</li>
            @endforeach
        </ul>
    @endif
    @if ($listing->status !== \App\Enums\PropertyStatus::Published)
        <form method="POST" action="{{ route('staff.listings.approve', $listing) }}" data-turbo-frame="_top" data-confirm="Approve this listing?" class="mt-4">
            @csrf
            <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Approve and publish</button>
        </form>
    @endif
</x-workspace-layout>
