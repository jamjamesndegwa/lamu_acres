<div class="mt-6 grid gap-4">
    @forelse ($comparisons as $comparison)
        <article class="rounded-2xl border border-sand-200 bg-white p-5">
            <h2 class="text-lg font-semibold text-lamu-900">
                <a class="hover:underline" href="{{ route($frame === 'workspace' ? 'investor.imagery.show' : 'intelligence.imagery.show', $comparison) }}">{{ $comparison->area_label }}</a>
            </h2>
        </article>
    @empty
        <p class="text-stone-600">No imagery comparison has been published. Nothing here is an automatic construction reading.</p>
    @endforelse
</div>
<div class="mt-4">{{ $comparisons->links() }}</div>
