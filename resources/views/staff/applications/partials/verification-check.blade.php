@php
    $currentOutcome = $profile[$check]['outcome'] ?? 'pending';
    $outcomeLabels = [
        'verified' => 'Verified by staff',
        'information_required' => 'Further information required',
        'rejected' => 'Rejected evidence',
        'pending' => 'Not reviewed yet',
    ];
@endphp
<fieldset class="rounded-xl border border-lamu-200 bg-white p-4 shadow-sm">
    <legend class="px-1 text-sm font-semibold text-lamu-900">{{ $label }}</legend>
    <p class="mt-1 text-sm text-stone-600">
        Current status:
        <span @class([
            'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
            'bg-emerald-100 text-emerald-900' => $currentOutcome === 'verified',
            'bg-amber-100 text-amber-900' => in_array($currentOutcome, ['pending', 'information_required'], true),
            'bg-red-100 text-red-900' => $currentOutcome === 'rejected',
        ])>{{ $outcomeLabels[$currentOutcome] ?? $currentOutcome }}</span>
        @if (! empty($profile[$check]['reviewed_at']))
            · Last updated {{ \Illuminate\Support\Carbon::parse($profile[$check]['reviewed_at'])->format('d M Y, H:i') }}
        @endif
    </p>
    <label class="mt-3 block text-sm font-medium text-lamu-900">Set review outcome
        <select name="{{ $check }}_outcome" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2 text-sm shadow-sm">
            <option value="">Keep existing review</option>
            @foreach (['verified' => 'Verified — documents accepted', 'information_required' => 'Ask applicant for more information', 'rejected' => 'Reject this evidence'] as $value => $name)
                <option value="{{ $value }}" @selected(old($check.'_outcome') === $value)>{{ $name }}</option>
            @endforeach
        </select>
    </label>
    <p class="mt-2 text-xs text-stone-500">Review the uploaded files above, then choose an outcome. No external reference numbers are required.</p>
</fieldset>
