@php
    $submitLabel = $submitLabel ?? 'Submit application';
@endphp

<form
    method="POST"
    action="{{ route('account.applications.store') }}"
    data-turbo-frame="_top"
    data-confirm="Submit this application?"
    class="space-y-4"
    id="capability-application-form"
>
    @csrf
    <input type="hidden" name="capability" value="{{ $capability->value }}">

    @if ($errors->any())
        <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-4">
            <p class="font-medium text-red-900">Please correct the following:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-800">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div>
        <label for="organization" class="block text-sm font-medium">Organisation</label>
        <input id="organization" name="organization" type="text" value="{{ old('organization', $application?->organization) }}" maxlength="160" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">
        @error('organization')
            <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label for="statement" class="block text-sm font-medium">Short statement <span class="text-red-700" aria-hidden="true">*</span></label>
        <textarea id="statement" name="statement" rows="4" required maxlength="1000" class="mt-1 w-full rounded-lg border border-sand-200 bg-white px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">{{ old('statement', $application?->statement) }}</textarea>
        @error('statement')
            <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror
        @error('capability')
            <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror
    </div>
    <button
        type="submit"
        class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white hover:bg-lamu-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800 disabled:cursor-not-allowed disabled:opacity-70"
        data-submit-label="{{ $submitLabel }}"
        data-submitting-label="Submitting…"
    >{{ $submitLabel }}</button>
</form>

<script>
    document.getElementById('capability-application-form')?.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.confirmed !== 'true') {
            return;
        }
        const button = form.querySelector('button[type="submit"]');
        if (button instanceof HTMLButtonElement && !button.disabled) {
            button.disabled = true;
            button.textContent = button.dataset.submittingLabel || 'Submitting…';
        }
    });
</script>
