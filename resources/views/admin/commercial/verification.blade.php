<x-crud-page title="Verification Standards" heading="Verification Standards">
    <p class="text-sm text-lamu-800/70">Levels and checklist categories used by the verification desk.</p>

    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-lamu-900">
        Missing configuration: verification checklist templates are not a managed contract. The categories below are the checklist already used on each property.
    </div>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-sand-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-lamu-900">Verification levels</h2>
            <ul class="mt-2 space-y-1 text-sm text-lamu-800/80">
                @foreach ($verificationLevels as $level)
                    <li>{{ $level->label() }}</li>
                @endforeach
            </ul>
        </div>
        <div class="rounded-lg border border-sand-200 bg-white p-4">
            <h2 class="text-sm font-semibold text-lamu-900">Checklist categories</h2>
            <ul class="mt-2 space-y-1 text-sm text-lamu-800/80">
                @foreach ($verificationCategories as $category)
                    <li>{{ $category->label() }}@if ($category->critical()) (critical)@endif</li>
                @endforeach
            </ul>
            <p class="mt-3 text-sm">
                @if ($canOpenVerification)
                    <a href="{{ route('staff.verification.index') }}" class="font-semibold text-lamu-800 underline">Open verification management</a>
                @else
                    Verification management is on the operations desk. This account is not authorized to open it.
                @endif
            </p>
        </div>
    </div>
</x-crud-page>
