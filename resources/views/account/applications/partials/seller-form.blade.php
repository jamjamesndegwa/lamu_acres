<form
    method="POST"
    enctype="multipart/form-data"
    action="{{ route('account.applications.store') }}"
    data-turbo-frame="_top"
    data-confirm="Submit this seller application?"
    class="space-y-8"
    id="seller-application-form"
>
    @csrf
    <input type="hidden" name="capability" value="{{ $capability->value }}">
    @include('account.applications.partials.verification-fields')

    @if ($errors->any())
        <div role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-4" tabindex="-1">
            <p class="font-medium text-red-900">Please correct the following:</p>
            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-800">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section aria-labelledby="seller-account-heading">
        <h2 id="seller-account-heading" class="text-lg font-semibold text-lamu-900">Your account</h2>
        <p class="mt-1 text-sm text-stone-600">Contact details come from your Lamu Acres login. They are not shown on public listings.</p>
        <dl class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-sand-200 bg-sand-50 px-4 py-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-stone-500">Name</dt>
                <dd class="mt-1 text-sm font-medium text-lamu-900">{{ auth()->user()->name }}</dd>
            </div>
            <div class="rounded-xl border border-sand-200 bg-sand-50 px-4 py-3">
                <dt class="text-xs font-medium uppercase tracking-wide text-stone-500">Email</dt>
                <dd class="mt-1 text-sm font-medium text-lamu-900">{{ auth()->user()->email }}</dd>
            </div>
        </dl>
    </section>

    <section aria-labelledby="seller-applicant-heading">
        <h2 id="seller-applicant-heading" class="text-lg font-semibold text-lamu-900">Applicant details</h2>
        <div class="mt-4">
            <label for="organization" class="block text-sm font-medium text-lamu-900">
                Organisation or holding name
                <span class="font-normal text-stone-500">(optional)</span>
            </label>
            <p id="organization-help" class="mt-1 text-sm text-stone-600">Use this if the land is held under a company, trust, or named estate.</p>
            <input
                id="organization"
                name="organization"
                type="text"
                value="{{ old('organization', $application?->organization) }}"
                maxlength="160"
                aria-describedby="organization-help"
                class="mt-2 w-full rounded-lg border border-sand-200 bg-white px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800"
            >
            @error('organization')
                <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
            @enderror
        </div>
    </section>

    <section aria-labelledby="seller-authority-heading">
        <h2 id="seller-authority-heading" class="text-lg font-semibold text-lamu-900">Ownership or authority to list</h2>
        <div class="mt-4">
            <label for="statement" class="block text-sm font-medium text-lamu-900">
                Short statement
                <span class="text-red-700" aria-hidden="true">*</span>
                <span class="sr-only">(required)</span>
            </label>
            <p id="statement-help" class="mt-1 text-sm text-stone-600">Describe your relationship to the land you intend to list—for example owner, co-owner, or authorized representative—and the general area you plan to submit.</p>
            <textarea
                id="statement"
                name="statement"
                rows="5"
                required
                maxlength="1000"
                aria-describedby="statement-help statement-count"
                class="mt-2 w-full rounded-lg border border-sand-200 bg-white px-3 py-2 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800"
            >{{ old('statement', $application?->statement) }}</textarea>
            <p id="statement-count" class="mt-1 text-xs text-stone-500">Maximum 1,000 characters.</p>
            @error('statement')
                <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
            @enderror
            @error('capability')
                <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
            @enderror
        </div>
    </section>

    <section aria-labelledby="seller-documents-heading">
        <h2 id="seller-documents-heading" class="text-lg font-semibold text-lamu-900">Supporting documents</h2>
        <p class="mt-2 text-sm text-stone-600">Identity and representative evidence is provided above for capability review. After approval, ownership documents and marketing authority are still reviewed separately for each property.</p>
    </section>

    <section aria-labelledby="seller-declarations-heading">
        <h2 id="seller-declarations-heading" class="text-lg font-semibold text-lamu-900">Declarations</h2>
        <p class="mt-2 text-sm text-stone-600">By submitting, you confirm the information above is accurate to the best of your knowledge. Staff may request evidence before granting seller access or publishing a listing. Submission does not guarantee approval.</p>
    </section>

    <div class="flex flex-wrap gap-3 border-t border-sand-200 pt-6">
        <button
            type="submit"
            class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white hover:bg-lamu-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800 disabled:cursor-not-allowed disabled:opacity-70"
            data-submit-label="Submit Seller Application"
            data-submitting-label="Submitting…"
        >Submit Seller Application</button>
        <a href="{{ route('list-property') }}" class="rounded-full border border-lamu-900 px-5 py-3 text-sm font-medium text-lamu-900 hover:bg-sand-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">Cancel</a>
    </div>
</form>

<script>
    document.getElementById('seller-application-form')?.addEventListener('submit', (event) => {
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
