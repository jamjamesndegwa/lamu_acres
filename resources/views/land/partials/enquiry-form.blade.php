<form method="POST" action="{{ route('land.enquiries.store', $property) }}" data-turbo-frame="_top" data-confirm="Send this enquiry?" class="space-y-4">
    @csrf
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
    <input type="hidden" name="referrer_url" value="{{ old('referrer_url', request('referrer_url')) }}">
    <input type="hidden" name="campaign" value="{{ old('campaign', request('utm_campaign', request('campaign'))) }}">

    <div>
        <label for="{{ $prefix }}message" class="block text-sm font-medium">Your question</label>
        <textarea id="{{ $prefix }}message" name="message" rows="5" required minlength="20" maxlength="2000" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">{{ old('message') }}</textarea>
        @error('message')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
        @error('website')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Send enquiry</button>
</form>

<script>
    document.querySelectorAll('input[name="referrer_url"]').forEach((input) => {
        if (!input.value && document.referrer) {
            input.value = document.referrer
        }
    })
</script>
