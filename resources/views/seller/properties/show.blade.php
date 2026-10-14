<x-workspace-layout :heading="$property->title">
    <p class="mt-3 text-sm text-stone-600">{{ $property->reference }} · {{ $verified ? 'Verified relationship' : 'Awaiting ownership verification' }}</p>
    <p class="mt-2 text-sm text-stone-700">{{ $passport['level'] }}</p>

    <section class="mt-6 max-w-3xl">
        <h2 class="font-semibold text-lamu-900">Verification summary</h2>
        <x-document-checklist
            empty="No verification summary has been recorded."
            :items="collect($passport['items'])->map(fn ($item) => ['label' => $item['category'], 'state' => $item['outcome']])->all()"
        />
    </section>

    <form method="POST" action="{{ route('seller.properties.documents.store', $property) }}" enctype="multipart/form-data" data-turbo-frame="_top" data-confirm="Store this ownership document?" class="mt-6 max-w-xl space-y-3">
        @csrf
        <x-upload-manager name="document" label="Ownership document" required />
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Upload privately</button>
    </form>

    @if ($verified)
        <form method="POST" action="{{ route('seller.properties.authority', $property) }}" data-turbo-frame="_top" data-confirm="Submit this marketing authority?" class="mt-6 max-w-xl space-y-3">
            @csrf
            <h2 class="font-semibold text-lamu-900">Marketing authority</h2>
            <textarea name="terms" required minlength="20" class="w-full rounded-lg border border-sand-200 px-3 py-2" placeholder="Authority to market this land"></textarea>
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Submit authority</button>
        </form>

        <form method="POST" action="{{ route('seller.properties.instructions', $property) }}" data-turbo-frame="_top" data-confirm="Record this instruction?" class="mt-6 max-w-xl space-y-3">
            @csrf
            <h2 class="font-semibold text-lamu-900">Sale instruction</h2>
            <select name="kind" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="listing">Listing</option>
                <option value="availability">Availability</option>
                <option value="viewing">Viewing</option>
                <option value="general">General</option>
            </select>
            <select name="requested_availability" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                <option value="">No availability change</option>
                <option value="available">Available</option>
                <option value="unavailable">Unavailable</option>
            </select>
            <textarea name="body" required minlength="5" class="w-full rounded-lg border border-sand-200 px-3 py-2"></textarea>
            <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Record instruction</button>
        </form>

        <section class="mt-8">
            <h2 class="font-semibold text-lamu-900">Instruction history</h2>
            <ol class="mt-3 space-y-2 text-sm">
                @foreach ($property->saleInstructions as $instruction)
                    <li>{{ $instruction->kind->label() }}: {{ $instruction->body }}</li>
                @endforeach
            </ol>
        </section>
    @endif
</x-workspace-layout>
