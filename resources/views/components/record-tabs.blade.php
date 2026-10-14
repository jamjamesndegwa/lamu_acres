@props([
    'tables' => [],
    'empty' => 'No activity yet',
])

@php
    $group = 'records-'.substr(md5(implode('|', array_column($tables, 'heading'))), 0, 8);
@endphp

@if (count($tables) === 1)
    @php $table = $tables[0]; @endphp
    <section class="mt-8" aria-labelledby="table-{{ \Illuminate\Support\Str::slug($table['heading']) }}">
        <h2 id="table-{{ \Illuminate\Support\Str::slug($table['heading']) }}" class="font-semibold text-lamu-900">{{ $table['heading'] }}</h2>
        @include('components.partials.record-table', ['table' => $table, 'empty' => $empty])
    </section>
@elseif (count($tables) > 1)
    @once
        <style>
            .record-tabs > input {
                position: absolute;
                width: 1px;
                height: 1px;
                padding: 0;
                margin: -1px;
                overflow: hidden;
                clip: rect(0, 0, 0, 0);
                border: 0;
            }
            .record-tabs .record-panel { display: none; }
            .record-tabs > input:nth-of-type(1):checked ~ .record-panels > .record-panel:nth-child(1),
            .record-tabs > input:nth-of-type(2):checked ~ .record-panels > .record-panel:nth-child(2),
            .record-tabs > input:nth-of-type(3):checked ~ .record-panels > .record-panel:nth-child(3),
            .record-tabs > input:nth-of-type(4):checked ~ .record-panels > .record-panel:nth-child(4),
            .record-tabs > input:nth-of-type(5):checked ~ .record-panels > .record-panel:nth-child(5),
            .record-tabs > input:nth-of-type(6):checked ~ .record-panels > .record-panel:nth-child(6) {
                display: block;
            }
            .record-tabs:has(> input:nth-of-type(1):checked) .record-tablist label:nth-of-type(1),
            .record-tabs:has(> input:nth-of-type(2):checked) .record-tablist label:nth-of-type(2),
            .record-tabs:has(> input:nth-of-type(3):checked) .record-tablist label:nth-of-type(3),
            .record-tabs:has(> input:nth-of-type(4):checked) .record-tablist label:nth-of-type(4),
            .record-tabs:has(> input:nth-of-type(5):checked) .record-tablist label:nth-of-type(5),
            .record-tabs:has(> input:nth-of-type(6):checked) .record-tablist label:nth-of-type(6) {
                border-bottom-color: #143028;
                font-weight: 500;
                color: #143028;
            }
        </style>
    @endonce

    <div class="record-tabs relative mt-8">
        <div class="record-tablist subnav-scroll flex w-full gap-1 overflow-x-auto border-b border-sand-200" role="tablist" aria-label="Records">
            @foreach ($tables as $index => $table)
                <label for="{{ $group }}-{{ $index }}" class="cursor-pointer whitespace-nowrap border-b-2 border-transparent px-3 py-3 text-sm text-stone-600 hover:text-lamu-900">{{ $table['heading'] }}</label>
            @endforeach
        </div>
        @foreach ($tables as $index => $table)
            <input type="radio" name="{{ $group }}" id="{{ $group }}-{{ $index }}" @checked($loop->first)>
        @endforeach
        <div class="record-panels">
            @foreach ($tables as $table)
                <section class="record-panel pt-4" aria-labelledby="table-{{ \Illuminate\Support\Str::slug($table['heading']) }}">
                    <h2 id="table-{{ \Illuminate\Support\Str::slug($table['heading']) }}" class="sr-only">{{ $table['heading'] }}</h2>
                    @include('components.partials.record-table', ['table' => $table, 'empty' => $empty])
                </section>
            @endforeach
        </div>
    </div>
@endif
