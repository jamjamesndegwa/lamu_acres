@props(['columns' => [], 'labelledBy' => null])

<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-2xl border border-sand-200 bg-white']) }}>
    <table class="min-w-full text-left text-sm" @if ($labelledBy) aria-labelledby="{{ $labelledBy }}" @endif>
        <thead class="border-b border-sand-200 text-stone-500">
            <tr>
                @foreach ($columns as $column)
                    <th scope="col" class="px-4 py-3 font-medium">{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
