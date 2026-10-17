@props([
    'name',
    'label',
    'accept' => null,
    'multiple' => false,
    'required' => false,
    'maxKilobytes' => null,
    'id' => null,
    'help' => null,
    'containerClass' => '',
])

@php
    $inputId = $id ?? 'upload-'.\Illuminate\Support\Str::slug($name);
    $helpId = $inputId.'-help';
@endphp

<div @class(['rounded-xl border border-dashed border-lamu-700/50 bg-lamu-100/30 p-3 transition hover:border-lamu-700 hover:bg-lamu-100/70 focus-within:border-lamu-700 focus-within:ring-2 focus-within:ring-lamu-700/20', $containerClass])>
    <div class="flex items-center gap-2.5">
        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white text-lamu-800 shadow-sm" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5M4 15.5v3A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5v-3" /></svg>
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <label for="{{ $inputId }}" class="text-sm font-semibold text-lamu-900">{{ $label }}</label>
                <span @class([
                    'text-xs font-semibold',
                    'text-red-700' => $required,
                    'text-stone-500' => ! $required,
                ])>{{ $required ? 'Required' : 'Optional' }}</span>
            </div>
            <p id="{{ $helpId }}" class="mt-0.5 text-xs leading-4 text-stone-600">
                {{ $help ?? ($multiple ? 'Select one or more files from your device.' : 'Choose a file from your device.') }}
            </p>
        </div>
    </div>

    <input
        id="{{ $inputId }}"
        name="{{ $name }}"
        type="file"
        @if ($accept) accept="{{ $accept }}" @endif
        @if ($multiple) multiple @endif
        @required($required)
        @if ($maxKilobytes) data-max-kilobytes="{{ $maxKilobytes }}" @endif
        aria-describedby="{{ $helpId }}"
        {{ $attributes->class(['mt-2.5 block w-full cursor-pointer rounded-lg border border-sand-200 bg-white text-sm text-stone-700 shadow-sm file:mr-2.5 file:cursor-pointer file:rounded-md file:border-0 file:bg-lamu-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white hover:file:bg-lamu-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700']) }}
    >
    @if ($maxKilobytes)
        <p data-upload-size-error hidden role="alert" class="mt-2 text-sm text-red-700"></p>
    @endif
</div>
@if (trim($slot) !== '')
    <div class="mt-3">{{ $slot }}</div>
@endif
