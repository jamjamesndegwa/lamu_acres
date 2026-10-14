<x-workspace-layout heading="Submit property">
    <form method="POST" action="{{ route('seller.properties.store') }}" data-turbo-frame="_top" data-confirm="Submit this property?" class="mt-6 max-w-xl space-y-4">
        @csrf
        <div>
            <label for="title" class="block text-sm font-medium">Title</label>
            <input id="title" name="title" required class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </div>
        <div>
            <label for="locality" class="block text-sm font-medium">Locality</label>
            <input id="locality" name="locality" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </div>
        <div>
            <label for="acreage" class="block text-sm font-medium">Acreage</label>
            <input id="acreage" name="acreage" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">
        </div>
        <div>
            <label for="summary" class="block text-sm font-medium">Summary</label>
            <textarea id="summary" name="summary" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2"></textarea>
        </div>
        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Submit property</button>
    </form>
    <section class="mt-8">
        <h2 class="font-semibold text-lamu-900">Submissions awaiting verification</h2>
        <ul class="mt-3 space-y-2 text-sm">
            @forelse ($submissions as $submission)
                <li><a class="text-lamu-800 hover:underline" href="{{ route('seller.properties.show', $submission) }}">{{ $submission->title }}</a></li>
            @empty
                <li class="text-stone-600">No submissions yet.</li>
            @endforelse
        </ul>
    </section>
</x-workspace-layout>
