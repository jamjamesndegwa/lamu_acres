@props(['title', 'description' => null, 'href' => null, 'frame' => null, 'icon' => null])

@php
    $iconClass = is_string($icon) && str_starts_with($icon, 'fa-') ? $icon : 'fa-folder-open';
@endphp

<section @class([
    'group flex h-full flex-col overflow-hidden rounded-2xl border bg-white shadow-sm transition',
    'border-sand-200 hover:border-lamu-300 hover:shadow-md' => filled($href),
    'border-dashed border-sand-300 bg-sand-50/50' => blank($href),
])>
    <div class="h-1.5 shrink-0 bg-lamu-800" aria-hidden="true"></div>
    <div class="flex flex-1 flex-col p-5">
        <div class="flex items-start gap-3">
            <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-lamu-900 text-base text-white shadow-sm" aria-hidden="true">
                <i class="fa-solid {{ $iconClass }}"></i>
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="home-serif text-lg font-semibold leading-snug text-lamu-900">{{ $title }}</h2>
                @if ($description)
                    <p class="mt-1.5 text-sm leading-relaxed text-stone-800">{{ $description }}</p>
                @endif
            </div>
        </div>
        <div class="mt-auto pt-5">
            @if ($href)
                <a
                    href="{{ $href }}"
                    @if ($frame) data-turbo-frame="{{ $frame }}" @endif
                    class="inline-flex items-center gap-2 rounded-full bg-lamu-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-lamu-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700"
                >
                    Open desk
                    <i class="fa-solid fa-arrow-right text-xs opacity-90" aria-hidden="true"></i>
                </a>
            @else
                <p class="rounded-xl border border-sand-200 bg-white px-4 py-3 text-sm text-stone-800">
                    <span class="font-semibold text-lamu-900">Coming soon</span>
                    <span class="mt-1 block text-stone-700">This module is not active yet. Your administrator can enable it when the desk goes live.</span>
                </p>
            @endif
        </div>
    </div>
</section>
