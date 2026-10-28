<section class="rounded-lg border border-sand-200 bg-white p-4 sm:p-5">
    <h3 class="text-sm font-semibold text-lamu-900">{{ $heading }}</h3>
    @if ($items->isEmpty())
        <p class="mt-2 text-xs leading-relaxed text-stone-600">{{ $empty }}</p>
        <a href="{{ route($browseRoute) }}" class="mt-3 inline-flex text-xs font-semibold text-lamu-800 underline">{{ $browseLabel }}</a>
    @else
        <ul class="mt-3 divide-y divide-sand-200">
            @foreach ($items as $item)
                <li class="py-3 first:pt-0 last:pb-0">
                    @if ($type === 'project')
                        <a href="{{ route('intelligence.projects.show', $item) }}" class="text-sm font-semibold text-lamu-900 hover:underline">{{ $item->name }}</a>
                        <p class="mt-1 text-xs leading-relaxed text-stone-600">{{ $item->summary }}</p>
                        <p class="mt-1 text-[11px] text-stone-500">
                            @if ($item->locality)
                                {{ $item->locality->name }}{{ $item->county ? ', '.$item->county->name : '' }}
                            @elseif ($item->coverage)
                                {{ $item->coverage }}
                            @endif
                            @if ($item->published_at)
                                · {{ $item->published_at->toFormattedDateString() }}
                            @endif
                            @if ($item->source_name)
                                · {{ $item->source_name }}
                            @endif
                        </p>
                    @elseif ($type === 'report')
                        <a href="{{ route('intelligence.reports.show', $item) }}" class="text-sm font-semibold text-lamu-900 hover:underline">{{ $item->title }}</a>
                        <p class="mt-1 text-xs leading-relaxed text-stone-600">{{ $item->summary }}</p>
                        <p class="mt-1 text-[11px] text-stone-500">
                            @if ($item->report_date)
                                {{ $item->report_date->toFormattedDateString() }}
                            @elseif ($item->published_at)
                                {{ $item->published_at->toFormattedDateString() }}
                            @endif
                        </p>
                    @else
                        <a href="{{ route('intelligence.imagery.show', $item) }}" class="text-sm font-semibold text-lamu-900 hover:underline">{{ $item->area_label }}</a>
                        @if ($item->observed_change)
                            <p class="mt-1 text-xs leading-relaxed text-stone-600">{{ $item->observed_change }}</p>
                        @endif
                        <p class="mt-1 text-[11px] text-stone-500">
                            @php
                                $scene = $item->beforeScene ?? $item->afterScene;
                            @endphp
                            @if ($scene?->locality)
                                {{ $scene->locality->name }}
                            @endif
                            @if ($item->published_at)
                                · {{ $item->published_at->toFormattedDateString() }}
                            @endif
                        </p>
                    @endif
                </li>
            @endforeach
        </ul>
        <a href="{{ route($browseRoute) }}" class="mt-3 inline-flex text-xs font-semibold text-lamu-800 underline">View all →</a>
    @endif
</section>
