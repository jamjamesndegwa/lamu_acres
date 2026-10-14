@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-stone-600">
            Showing
            <span class="font-medium text-stone-900">{{ $paginator->firstItem() }}</span>
            to
            <span class="font-medium text-stone-900">{{ $paginator->lastItem() }}</span>
            of
            <span class="font-medium text-stone-900">{{ $paginator->total() }}</span>
        </p>
        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="rounded-md px-3 py-2 text-sm text-stone-400">Previous</span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    rel="prev"
                    @if (! empty($frame)) data-turbo-frame="{{ $frame }}" @endif
                    class="rounded-md px-3 py-2 text-sm text-lamu-800 hover:bg-lamu-100"
                >Previous</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 text-sm text-stone-400">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="rounded-md bg-lamu-900 px-3 py-2 text-sm text-white">{{ $page }}</span>
                        @else
                            <a
                                href="{{ $url }}"
                                @if (! empty($frame)) data-turbo-frame="{{ $frame }}" @endif
                                class="rounded-md px-3 py-2 text-sm text-lamu-800 hover:bg-lamu-100"
                            >{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    rel="next"
                    @if (! empty($frame)) data-turbo-frame="{{ $frame }}" @endif
                    class="rounded-md px-3 py-2 text-sm text-lamu-800 hover:bg-lamu-100"
                >Next</a>
            @else
                <span class="rounded-md px-3 py-2 text-sm text-stone-400">Next</span>
            @endif
        </div>
    </nav>
@endif
