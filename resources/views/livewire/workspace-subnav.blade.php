<div class="min-w-0 shrink-0">
    @if (count($items) > 0)
        <nav id="workspace-subnav" class="subnav-scroll shrink-0 border-b border-sand-200 bg-white" aria-label="{{ $moduleLabel }}">
            <div class="flex w-max min-w-full items-stretch px-2 sm:px-6">
                @foreach ($items as $item)
                    @if ($item['method'] === 'post' && $item['href'])
                        <form method="POST" action="{{ $item['href'] }}" data-turbo="false" data-confirm="Log out of Lamu Acres?">
                            @csrf
                            <button type="submit" class="whitespace-nowrap border-b-2 border-transparent px-3 py-3 text-sm text-stone-600 hover:border-sand-200 hover:text-lamu-900">
                                {{ $item['label'] }}
                            </button>
                        </form>
                    @elseif ($item['available'])
                        <a
                            href="{{ $item['href'] }}"
                            @if ($item['active']) aria-current="page" @endif
                            @if ($item['frame']) data-turbo-frame="{{ $item['frame'] }}" @endif
                            @class([
                                'whitespace-nowrap border-b-2 px-3 py-3 text-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700',
                                'border-lamu-700 font-medium text-lamu-900' => $item['active'],
                                'border-transparent text-stone-600 hover:border-sand-200 hover:text-lamu-900' => ! $item['active'],
                            ])
                        >
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span aria-disabled="true" class="inline-flex items-baseline gap-2 whitespace-nowrap border-b-2 border-transparent px-3 py-3 text-sm text-stone-400" title="Not available yet">
                            <span>{{ $item['label'] }}</span>
                            <span class="text-[11px] uppercase tracking-wide">Unavailable</span>
                        </span>
                    @endif
                @endforeach
            </div>
        </nav>
    @endif
</div>
