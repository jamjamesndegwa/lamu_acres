@if ($subject->publication_status === \App\Enums\PublicationStatus::Published && auth()->user()->hasPermission('intelligence.publish'))
    <form method="POST" action="{{ $action }}" data-confirm="Withdraw {{ $subject->reference }} from public and premium access?">
        @csrf
        <button type="submit" class="rounded-full border border-amber-800 px-4 py-2 text-sm text-amber-900">Withdraw</button>
    </form>
@endif
