@if ($user->pending_phone)
    <form method="POST" action="{{ route('account.phone.confirm') }}" @if ($breakout ?? true) data-turbo-frame="_top" @endif data-confirm="Confirm this phone number?" class="mt-6 space-y-3 border-t border-sand-200 pt-6">
        @csrf
        <h3 class="font-semibold text-lamu-900">Confirm phone</h3>
        <p class="text-sm text-stone-600">Enter the code for {{ $user->pending_phone }}.</p>
        <label for="{{ $prefix }}code" class="block text-sm font-medium">Code</label>
        <input id="{{ $prefix }}code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" class="w-full rounded-lg border border-sand-200 px-3 py-2">
        @error('code')
            <p class="text-sm text-red-700">{{ $message }}</p>
        @enderror
        <button type="submit" class="rounded-full border border-lamu-900 px-5 py-3 text-sm font-medium text-lamu-900">Confirm phone</button>
    </form>
@endif
