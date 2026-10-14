@php
    use App\Enums\ListingPurpose;

    $tone = $tone ?? 'light';
    $idPrefix = $idPrefix ?? 'cta';
    $isDark = $tone === 'dark';
@endphp

@if ($primaryCta['type'] === 'start')
    <form
        method="GET"
        action="{{ route('list-property.start') }}"
        class="@if ($isDark) mx-auto max-w-xl @else max-w-xl @endif space-y-4 rounded-2xl border p-5 @if ($isDark) border-white/20 bg-lamu-800/80 @else border-sand-200 bg-sand-50 @endif"
    >
        @if ($primaryCta['requiresPurpose'])
            <fieldset>
                <legend class="text-sm font-medium @if ($isDark) text-white @else text-lamu-900 @endif">Listing intent</legend>
                <div class="mt-3 flex flex-wrap gap-4 text-sm @if ($isDark) text-sand-100 @endif">
                    @foreach (ListingPurpose::cases() as $purpose)
                        <label class="flex min-h-11 cursor-pointer items-center gap-2">
                            <input
                                type="radio"
                                name="purpose"
                                value="{{ $purpose->value }}"
                                @checked(old('purpose', ListingPurpose::Sale->value) === $purpose->value)
                                required
                                class="size-4 border-sand-300 text-lamu-900 focus:ring-lamu-800"
                            >
                            {{ $purpose->label() }}
                        </label>
                    @endforeach
                </div>
                @error('purpose')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </fieldset>
        @endif
        <button
            type="submit"
            class="w-full rounded-full px-5 py-3 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800 sm:w-auto @if ($isDark) bg-white text-lamu-900 hover:bg-sand-100 @else bg-lamu-900 text-white hover:bg-lamu-800 @endif"
        >{{ $primaryCta['label'] }}</button>
        <p class="text-xs @if ($isDark) text-sand-200 @else text-stone-600 @endif">{{ $primaryCta['helper'] }}</p>
    </form>
@else
    <div class="@if ($isDark) mx-auto max-w-xl text-center @else max-w-xl @endif">
        <a
            href="{{ $primaryCta['href'] }}"
            class="inline-flex min-h-11 items-center justify-center rounded-full px-5 py-3 text-sm font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800 @if ($isDark) bg-white text-lamu-900 hover:bg-sand-100 @else bg-lamu-900 text-white hover:bg-lamu-800 @endif"
        >{{ $primaryCta['label'] }}</a>
        <p class="mt-3 text-xs @if ($isDark) text-sand-200 @else text-stone-600 @endif">{{ $primaryCta['helper'] }}</p>
    </div>
@endif
