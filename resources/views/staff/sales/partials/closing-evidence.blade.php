<h2 class="mt-8 font-semibold text-lamu-900">Closing evidence</h2>
@foreach ($evidenceCatalog as $code => $label)
    @php($evidenceItem = $transaction->evidence->firstWhere('code', $code))
    <form method="POST" action="{{ route('staff.sales.evidence', $transaction) }}" data-turbo-frame="_top" data-confirm="Update this closing document?" class="mt-3 flex flex-wrap items-center gap-2 text-sm">
        @csrf
        <input type="hidden" name="code" value="{{ $code }}">
        <span class="w-40">{{ $label }}</span>
        <span class="text-stone-500">{{ $evidenceItem?->state?->label() ?? 'Not recorded' }}</span>
        <select name="state" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
            @foreach (\App\Enums\ClosingEvidenceState::cases() as $state)
                <option value="{{ $state->value }}" @selected($evidenceItem?->state === $state)>{{ $state->label() }}</option>
            @endforeach
        </select>
        <button class="rounded-full border border-sand-200 px-4 py-2">Save</button>
    </form>
@endforeach
