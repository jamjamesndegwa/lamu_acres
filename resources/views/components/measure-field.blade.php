@props([
    'name',
    'label',
    'unit' => null,
    'value' => null,
    'required' => false,
    'inputmode' => 'decimal',
])

<label class="block text-sm">
    <span class="font-medium">{{ $label }}@if ($unit) ({{ $unit }})@endif</span>
    <input
        name="{{ $name }}"
        value="{{ $value }}"
        inputmode="{{ $inputmode }}"
        @required($required)
        {{ $attributes->merge(['class' => 'mt-1 w-full rounded-lg border border-sand-200 px-3 py-2']) }}
    >
</label>
