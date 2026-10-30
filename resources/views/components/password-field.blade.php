@props([
    'name',
    'id' => null,
    'label',
    'required' => false,
    'autocomplete' => 'current-password',
    'value' => '',
])

@php
    $inputId = $id ?? $name;
@endphp

<div>
    <label for="{{ $inputId }}" class="block text-sm font-medium">{{ $label }}</label>
    <div class="relative mt-2" data-password-field>
        <input
            type="password"
            id="{{ $inputId }}"
            name="{{ $name }}"
            @if ($required) required @endif
            autocomplete="{{ $autocomplete }}"
            @if ($value !== '') value="{{ $value }}" @endif
            {{ $attributes->class(['h-12 w-full rounded-lg border border-sand-200 bg-white py-0 pl-3 pr-11 focus:border-lamu-800 focus:outline-none focus:ring-2 focus:ring-lamu-100']) }}
        />
        <button
            type="button"
            data-password-toggle-btn
            class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-lg text-stone-500 hover:text-lamu-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700"
            aria-label="Show password"
            aria-pressed="false"
        >
            <i class="fa-regular fa-eye text-base" data-icon-show aria-hidden="true"></i>
            <i class="fa-regular fa-eye-slash hidden text-base" data-icon-hide aria-hidden="true"></i>
        </button>
    </div>
    @error($name)
        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
