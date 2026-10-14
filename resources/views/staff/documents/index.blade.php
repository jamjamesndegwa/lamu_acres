<x-workspace-layout heading="Document access">
    <p class="mt-3 max-w-3xl text-stone-700">Approving documents does not release exact property directions.</p>
    <div class="mt-6 space-y-4">
        @forelse ($requests as $accessRequest)
            <article class="rounded-2xl border border-sand-200 bg-white p-5">
                <p class="font-medium text-lamu-900">{{ $accessRequest->property->title }}</p>
                <p class="mt-1 text-sm text-stone-600">{{ $accessRequest->buyer->name }} · {{ $accessRequest->status->label() }}</p>
                @if ($accessRequest->note)
                    <p class="mt-2 text-sm text-stone-700">{{ $accessRequest->note }}</p>
                @endif
                @if ($accessRequest->status->value === 'requested')
                    <form method="POST" action="{{ route('staff.documents.update', $accessRequest) }}" data-turbo-frame="_top" data-confirm="Decide this document request?" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        @method('PUT')
                        <select name="status" class="rounded-lg border border-sand-200 bg-white px-3 py-2">
                            <option value="approved">Approved</option>
                            <option value="declined">Declined</option>
                        </select>
                        <button class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save decision</button>
                    </form>
                @endif
            </article>
        @empty
            <x-workspace.empty title="No document requests" message="Buyer requests will appear here." />
        @endforelse
    </div>
    <div class="mt-4"><x-workspace.pagination :paginator="$requests" /></div>
</x-workspace-layout>
