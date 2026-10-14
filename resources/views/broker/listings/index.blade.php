<x-workspace-layout heading="My listings">
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-3xl text-stone-700">Land you have submitted. A submission stays off the public directory until staff approve it.</p>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('broker.listings.index') }}" @class(['rounded-full px-3 py-1', 'bg-lamu-900 text-white' => ! $approvedOnly, 'border border-sand-200' => $approvedOnly])>All</a>
            <a href="{{ route('broker.listings.index', ['approved' => 1]) }}" @class(['rounded-full px-3 py-1', 'bg-lamu-900 text-white' => $approvedOnly, 'border border-sand-200' => ! $approvedOnly])>Approved</a>
            <a href="{{ route('broker.listings.create') }}" class="rounded-full bg-lamu-900 px-4 py-1 font-medium text-white">Submit property</a>
        </div>
    </div>

    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Status', 'Location', 'Price', 'Verification']">
            @forelse ($listings as $listing)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route('broker.listings.show', $listing) }}" class="font-medium text-lamu-900 hover:underline">{{ $listing->title }}</a>
                        <p class="text-xs text-stone-500">{{ $listing->reference }}</p>
                    </td>
                    <td class="px-4 py-4">{{ $listing->status->label() }}</td>
                    <td class="px-4 py-4 text-stone-600">{{ $listing->locationLabel() }}</td>
                    <td class="px-4 py-4">{{ $listing->formattedPrice() ?? 'Not recorded' }}</td>
                    <td class="px-4 py-4">{{ $listing->verification_level?->label() ?? 'Not reviewed' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8">
                        <x-workspace.empty title="No submissions yet" message="Submit land when you have authority to market it. Nothing is published automatically." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4">
            <x-workspace.pagination :paginator="$listings" />
        </div>
    </div>
</x-workspace-layout>
