<x-workspace-layout heading="Delivery history">
    <div class="mt-6 space-y-3">
        @forelse ($deliveries as $delivery)
            <article class="rounded-2xl border border-sand-200 bg-white p-4 text-sm">
                <p class="font-medium text-lamu-900">{{ $delivery->event->label() }} · {{ $delivery->channel->label() }} · {{ $delivery->status }}</p>
                <p class="mt-1">{{ $delivery->recipient?->name }} · {{ $delivery->subject }}</p>
                <p class="mt-1 text-stone-700">{{ $delivery->body }}</p>
                @if (str_starts_with((string) $delivery->destination, 'https://wa.me/'))
                    <p class="mt-1"><a href="{{ $delivery->destination }}" class="text-lamu-900 underline">Open WhatsApp chat</a></p>
                @endif
                <p class="mt-1 text-xs text-stone-500">Attempts {{ $delivery->attempts }}@if ($delivery->error) · {{ $delivery->error }}@endif</p>
            </article>
        @empty
            <x-workspace.empty title="No deliveries" message="Messages appear here after an account, enquiry, or sale event." />
        @endforelse
    </div>
    <div class="mt-6">{{ $deliveries->links() }}</div>
</x-workspace-layout>
