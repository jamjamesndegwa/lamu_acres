<x-public-layout title="Land in Lamu — Lamu Acres">
    <section class="mx-auto max-w-6xl px-4 py-12 sm:px-6">
        <h1 class="text-3xl font-semibold text-lamu-900">Land in Lamu</h1>
        <p class="mt-2 max-w-2xl text-stone-600">Published listings can be read without an account. Saving a property or sending an enquiry uses your buyer account.</p>

        <div class="mt-8 grid gap-4 md:grid-cols-2">
            @forelse ($properties as $property)
                <article class="rounded-2xl border border-sand-200 bg-white p-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-500">{{ $property->reference }}</p>
                    <h2 class="mt-2 text-lg font-semibold text-lamu-900">
                        <a href="{{ $property->publicUrl() }}" class="hover:underline">{{ $property->title }}</a>
                    </h2>
                    <p class="mt-2 text-sm text-stone-600">{{ $property->locationLabel() ?: 'Lamu' }}</p>
                </article>
            @empty
                <x-workspace.empty title="No published land yet" message="Listings appear here after they are published." />
            @endforelse
        </div>

        <div class="mt-6">
            <x-workspace.pagination :paginator="$properties" />
        </div>
    </section>
</x-public-layout>
