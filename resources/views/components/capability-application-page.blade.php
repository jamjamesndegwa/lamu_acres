@props([
    'title',
    'description',
    'eyebrow',
    'heading',
    'intro',
    'backHref',
    'backLabel',
    'application' => null,
    'helpHref' => null,
    'helpLabel' => null,
    'heroTone' => 'dark',
])

<x-public-layout :title="$title" :description="$description">
    @if ($heroTone === 'light')
        <section class="border-b border-sand-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <p class="text-sm font-medium uppercase tracking-wide text-gold-500">{{ $eyebrow }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-lamu-900 sm:text-4xl">{{ $heading }}</h1>
                <p class="mt-4 max-w-2xl text-stone-700">{{ $intro }}</p>
            </div>
        </section>
    @else
        <section class="bg-lamu-900 text-white">
            <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <p class="text-sm font-medium uppercase tracking-wide text-gold-500">{{ $eyebrow }}</p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">{{ $heading }}</h1>
                <p class="mt-4 max-w-2xl text-sand-50">{{ $intro }}</p>
            </div>
        </section>
    @endif

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
        {{ $breadcrumb ?? '' }}

        <p class="mt-4">
            <a href="{{ $backHref }}" class="inline-flex items-center gap-1 text-sm font-medium text-lamu-800 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-800">
                <span aria-hidden="true">←</span> {{ $backLabel }}
            </a>
        </p>

        @if ($application)
            <div class="mt-6 rounded-2xl border border-sand-200 bg-white p-5">
                <p class="text-sm text-stone-700">
                    Current status:
                    <span class="font-semibold text-lamu-900">{{ $application->status->label() }}</span>
                </p>
                @if ($application->decision_reason)
                    <p class="mt-2 text-sm text-stone-600">{{ $application->decision_reason }}</p>
                @endif
                @unless ($application->status->isOpenForApplicant())
                    <p class="mt-3 text-sm text-stone-600">
                        <a href="{{ route('account.applications.index') }}" class="font-medium text-lamu-800 hover:underline">View all applications</a>
                    </p>
                @endunless
            </div>
        @endif

        <div @class(['mt-8 gap-8', 'lg:grid lg:grid-cols-3' => isset($guidance)])>
            <div @class(['min-w-0', 'lg:col-span-2' => isset($guidance)])>
                {{ $form }}
            </div>
            @isset($guidance)
                <aside class="min-w-0 lg:col-span-1">
                    {{ $guidance }}
                </aside>
            @endisset
        </div>

        @if ($helpHref && $helpLabel)
            <p class="mt-6 text-sm text-stone-600">
                <a href="{{ $helpHref }}" class="font-medium text-lamu-800 hover:underline">{{ $helpLabel }}</a>
            </p>
        @endif
    </div>
</x-public-layout>
