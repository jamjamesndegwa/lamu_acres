<x-workspace-layout heading="Delivery history">
    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
            <p class="font-medium text-lamu-900">SMS provider</p>
            <p class="mt-1 text-stone-700">{{ $smsConfigured ? 'Configured' : 'Not configured' }}</p>
        </article>
        <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
            <p class="font-medium text-lamu-900">SMTP mail</p>
            <p class="mt-1 text-stone-700">{{ ($mailStatus['smtp_ready'] ?? false) ? 'Ready' : 'Needs setup' }}</p>
        </article>
        <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
            <p class="font-medium text-lamu-900">Queue</p>
            <p class="mt-1 text-stone-700">
                @if ($pendingJobs !== null)
                    {{ $pendingJobs }} pending@if ($failedJobs !== null) · {{ $failedJobs }} failed@endif
                @else
                    {{ $mailStatus['queue_connection'] ?? 'sync' }} driver
                @endif
            </p>
        </article>
        <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
            <p class="font-medium text-lamu-900">Uncertain SMS</p>
            <p class="mt-1 text-stone-700">{{ $uncertainCount }} awaiting confirmation</p>
        </article>
    </div>
    <div class="mt-6 space-y-3">
        @forelse ($deliveries as $delivery)
            <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                <p class="font-medium text-lamu-900">
                    {{ $delivery->event->label() }} · {{ $delivery->channel->label() }} ·
                    @if ($delivery->status === 'uncertain')
                        <span class="text-amber-800">uncertain (provider outcome unknown)</span>
                    @else
                        {{ $delivery->status }}
                    @endif
                </p>
                <p class="mt-1">{{ $delivery->recipient?->name }} · {{ $delivery->subject }}</p>
                <p class="mt-1 text-stone-700">{{ $delivery->body }}</p>
                @if (str_starts_with((string) $delivery->destination, 'https://wa.me/'))
                    <p class="mt-1"><a href="{{ $delivery->destination }}" class="text-lamu-900 underline">Open WhatsApp chat</a></p>
                @endif
                <p class="mt-1 text-xs text-stone-500">
                    Attempts {{ $delivery->attempts }}
                    @if ($delivery->provider_reference)
                        · Provider {{ ($delivery->provider_meta['reference_kind'] ?? 'message') === 'batch' ? 'batch' : 'message' }} ref {{ $delivery->provider_reference }}
                    @endif
                    @if ($delivery->error) · {{ $delivery->error }}@endif
                </p>
            </article>
        @empty
            <x-workspace.empty title="No deliveries" message="Messages appear here after an account, enquiry, or sale event." />
        @endforelse
    </div>
    <div class="mt-6">{{ $deliveries->links() }}</div>
</x-workspace-layout>
