@if ($frame === 'workspace')
    <x-workspace-layout :heading="$heading">
        <p class="mt-3 max-w-3xl text-stone-700">{{ $description }}</p>
        @include($partial)
    </x-workspace-layout>
@else
    <x-public-layout :title="$heading.' — Lamu Acres'" :description="$description">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
            <h1 class="text-3xl font-semibold text-lamu-900">{{ $heading }}</h1>
            <p class="mt-3 max-w-3xl text-stone-700">{{ $description }}</p>
            @include($partial)
        </div>
    </x-public-layout>
@endif
