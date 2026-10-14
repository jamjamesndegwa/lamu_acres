<x-workspace-layout :heading="$listing->title">
    <p class="mt-3 text-sm text-stone-600">{{ $listing->reference }} · {{ $listing->status->label() }} · {{ $listing->locationLabel() }}</p>
    @if ($listing->status !== \App\Enums\PropertyStatus::Published)
        <p class="mt-3 max-w-3xl text-stone-700">This submission is not on the public directory. Staff have to approve it first.</p>
    @else
        <p class="mt-3 text-stone-700">This listing is approved and published. <a class="font-medium text-lamu-800" href="{{ $listing->publicUrl() }}">View the public page</a></p>
    @endif

    @if ($warnings !== [])
        <ul class="mt-4 max-w-3xl list-disc space-y-1 rounded-2xl border border-sand-200 bg-white px-8 py-4 text-sm text-stone-700">
            @foreach ($warnings as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    @endif

    <dl class="mt-6 grid max-w-3xl gap-4 rounded-2xl border border-sand-200 bg-white p-5 sm:grid-cols-2">
        <div>
            <dt class="text-sm text-stone-500">Seller relationship</dt>
            <dd>{{ $listing->marketing_role?->label() }}</dd>
        </div>
        <div>
            <dt class="text-sm text-stone-500">Seller</dt>
            <dd>{{ $listing->seller_name }}</dd>
        </div>
        <div>
            <dt class="text-sm text-stone-500">Seller contact</dt>
            <dd>{{ $listing->seller_contact }}</dd>
        </div>
        <div>
            <dt class="text-sm text-stone-500">Parcel</dt>
            <dd>{{ $listing->parcel_identifier ?: 'Not supplied' }}</dd>
        </div>
    </dl>

    <h2 class="mt-8 font-semibold text-lamu-900">Files</h2>
    <ul class="mt-3 space-y-1 text-sm text-stone-700">
        @foreach ($listing->media as $file)
            <li>
                <a class="font-medium text-lamu-800 hover:underline" href="{{ route('broker.listings.media', [$listing, $file]) }}">{{ $file->kind === 'authority' ? 'Marketing authority' : 'Photograph' }}: {{ $file->original_name }}</a>
            </li>
        @endforeach
    </ul>

    <h2 class="mt-8 font-semibold text-lamu-900">Requests for information</h2>
    <div class="mt-3 max-w-3xl space-y-4">
        @forelse ($listing->informationRequests as $item)
            <article class="rounded-2xl border border-sand-200 bg-white p-4">
                <p class="text-sm text-stone-500">Staff asked</p>
                <p class="mt-1">{{ $item->question }}</p>
                @if ($item->response)
                    <p class="mt-3 text-sm text-stone-500">Your reply</p>
                    <p>{{ $item->response }}</p>
                @else
                    <form method="POST" action="{{ route('broker.listings.respond', [$listing, $item]) }}" class="mt-3 space-y-2">
                        @csrf
                        <label class="block text-sm font-medium" for="response-{{ $item->id }}">Reply</label>
                        <textarea id="response-{{ $item->id }}" name="response" rows="3" required class="w-full rounded-lg border border-sand-200 px-3 py-2"></textarea>
                        <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Send reply</button>
                    </form>
                @endif
            </article>
        @empty
            <p class="text-sm text-stone-600">Staff have not asked for more information.</p>
        @endforelse
    </div>

    <h2 class="mt-8 font-semibold text-lamu-900">History</h2>
    <ul class="mt-3 space-y-2 text-sm text-stone-700">
        @forelse ($listing->audits as $audit)
            <li>{{ $audit->created_at?->toDayDateTimeString() }} · {{ str_replace('_', ' ', $audit->action) }}</li>
        @empty
            <li>No history yet.</li>
        @endforelse
    </ul>
</x-workspace-layout>
