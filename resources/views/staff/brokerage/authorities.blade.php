<x-workspace-layout heading="Seller authority">
    <p class="mt-3 max-w-3xl text-stone-700">Marketing authority submitted by landowners.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Seller', 'Status']">
            @forelse ($authorities as $authority)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">{{ $authority->property->title }}</td>
                    <td class="px-4 py-4 text-sm">{{ $authority->seller->name }}</td>
                    <td class="px-4 py-4 text-sm">{{ $authority->status->label() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8">
                        <x-workspace.empty title="No authority on file" message="A landowner submission will appear here when it is sent for review." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4">{{ $authorities->links() }}</div>
    </div>
</x-workspace-layout>
