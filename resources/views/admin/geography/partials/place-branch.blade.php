<li class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
    <p class="font-medium text-lamu-900">{{ $place->name }} <span class="font-normal text-stone-500">· {{ $place->kind->label() }}</span></p>
    <p class="mt-1 text-stone-600">
        {{ $place->hasPoint() ? 'Named point stored' : 'Named point not stored' }}
        · {{ $place->hasBoundary() ? 'verified boundary stored' : 'boundary not stored' }}
    </p>
    @php($children = $places->where('parent_id', $place->id))
    @if ($children->isNotEmpty())
        <ul class="mt-3 space-y-3 border-l border-sand-200 pl-4">
            @foreach ($children as $child)
                @include('admin.geography.partials.place-branch', ['place' => $child, 'places' => $places])
            @endforeach
        </ul>
    @endif
</li>
