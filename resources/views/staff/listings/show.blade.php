<x-workspace-layout :heading="$listing->title">
    <p class="mt-3 text-sm text-stone-600">{{ $listing->reference }} · {{ $listing->status->label() }} · submitted by {{ $listing->submitter?->name }}@if ($listing->submitter?->email) · {{ $listing->submitter->email }}@endif</p>
    <p class="mt-2 text-sm"><a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.properties.show', $listing) }}">Open property workspace</a></p>
    <dl class="mt-6 grid max-w-3xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2">
        <div><dt class="text-sm text-stone-500">Seller</dt><dd>{{ $listing->seller_name ?? $listing->submitter?->name }}</dd></div>
        <div><dt class="text-sm text-stone-500">Contact</dt><dd>{{ $listing->seller_contact ?? $listing->submitter?->email }}</dd></div>
        <div><dt class="text-sm text-stone-500">Relationship</dt><dd>{{ $listing->marketing_role?->label() }}</dd></div>
        <div><dt class="text-sm text-stone-500">Parcel</dt><dd>{{ $listing->parcel_identifier ?: 'Not supplied' }}</dd></div>
        <div class="sm:col-span-2"><dt class="text-sm text-stone-500">Location</dt><dd>{{ $listing->locationLabel() }} · {{ $listing->acreage }} acres</dd></div>
        @foreach ($listing->owners as $owner)
            <div class="sm:col-span-2"><dt class="text-sm text-stone-500">Declared relationship</dt><dd>{{ $owner->user?->name ?? 'Account' }} · {{ $owner->relationship->label() }} · {{ $owner->status->label() }}@if ($owner->status !== \App\Enums\OwnerLinkStatus::Verified) · <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.properties.show', [$listing, 'tab' => 'owners']) }}">verify on the owners tab</a>@endif</dd></div>
        @endforeach
        @foreach ($listing->authorities as $authority)
            <div class="sm:col-span-2"><dt class="text-sm text-stone-500">Marketing authority</dt><dd>{{ $authority->status->label() }} · {{ $authority->scope?->label() ?? 'Sale and lease' }}@if ($authority->valid_until) · until {{ $authority->valid_until->format('j M Y') }}@endif · <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.properties.show', [$listing, 'tab' => 'marketing']) }}">decide on the marketing tab</a></dd></div>
        @endforeach
    </dl>
    @if ($listing->summary)
        <p class="mt-4 max-w-3xl font-medium text-stone-800">{{ $listing->summary }}</p>
    @endif
    @if ($listing->description)
        <p class="mt-2 max-w-3xl text-stone-700">{{ $listing->description }}</p>
    @endif
    @include('staff.properties.partials.listing-details', ['property' => $listing])
    @if ($listing->owners->isNotEmpty())
        @include('staff.properties.partials.ownership-documents', ['property' => $listing])
    @endif
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
        @forelse ($listing->media as $file)
            <li>
                @if (str_starts_with((string) $file->path, 'broker-listings/'.$listing->id.'/'))
                    <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.listings.media', [$listing, $file]) }}">{{ $file->kind === 'authority' ? 'Marketing authority' : 'Photograph' }}: {{ $file->original_name }}</a>
                @elseif (\App\Services\Media\PropertyMediaLibrary::isPublicDerivative($listing, $file))
                    <a class="font-medium text-lamu-800 hover:underline" href="{{ route('staff.properties.media.preview', [$listing, $file]) }}">Photograph{{ $file->is_cover ? ' (cover)' : '' }}: {{ $file->original_name }}</a>
                @endif
            </li>
        @empty
            <li class="text-stone-600">No files uploaded.</li>
        @endforelse
    </ul>
    <h2 class="mt-8 font-semibold text-lamu-900">Information requests</h2>
    <div class="mt-3 max-w-3xl space-y-3">
        @forelse ($listing->informationRequests as $item)
            <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                <p>{{ $item->question }}</p>
                @if ($item->response)
                    <p class="mt-2 text-stone-600">Reply: {{ $item->response }}</p>
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
        @include('admin.approvals.return-field')
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
            @include('admin.approvals.return-field')
            <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Approve and publish</button>
        </form>
    @endif
</x-workspace-layout>
