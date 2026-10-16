<fieldset class="space-y-3 rounded-xl border border-sand-200 bg-sand-50 p-4">
    <legend class="px-2 text-sm font-semibold text-lamu-900">{{ $label }}</legend>
    <p class="text-xs text-stone-600">Current outcome: {{ $profile[$check]['outcome'] ?? 'Not reviewed' }} · Reviewer {{ $profile[$check]['reviewed_by'] ?? 'Not recorded' }} · {{ $profile[$check]['reviewed_at'] ?? 'No review date' }}</p>
    <p class="text-xs text-stone-600">Reference: {{ $profile[$check]['reference'] ?? 'Not recorded' }} · Next review: {{ $profile[$check]['next_review_on'] ?? 'Not set' }}</p>
    <label class="block text-sm">Review outcome
        <select name="{{ $check }}_outcome" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
            <option value="">Keep existing review</option>
            @foreach (['verified' => 'Verified by staff', 'information_required' => 'Further information required', 'rejected' => 'Rejected evidence'] as $value => $name)
                <option value="{{ $value }}" @selected(old($check.'_outcome') === $value)>{{ $name }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm">Evidence / authoritative record reference
        <input name="{{ $check }}_reference" minlength="8" maxlength="500" value="{{ old($check.'_reference') }}" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
    </label>
    <label class="block text-sm">Next review date (required when verified)
        <input type="date" name="{{ $check }}_next_review_on" min="{{ today('Africa/Nairobi')->toDateString() }}" value="{{ old($check.'_next_review_on') }}" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2">
    </label>
</fieldset>
