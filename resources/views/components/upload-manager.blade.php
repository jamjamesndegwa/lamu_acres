@props([
    'name',
    'label',
    'accept' => null,
    'multiple' => false,
    'required' => false,
])

<label class="block text-sm">
    <span class="font-medium">{{ $label }}</span>
    <input
        type="file"
        name="{{ $name }}"
        @if ($accept) accept="{{ $accept }}" @endif
        @if ($multiple) multiple @endif
        @required($required)
        class="mt-1 block w-full text-sm"
    >
</label>
@if (trim($slot) !== '')
    <div class="mt-3">{{ $slot }}</div>
@endif
