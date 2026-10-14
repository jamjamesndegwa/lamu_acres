<x-workspace-layout heading="Commission agreements">
    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Status', 'Broker', 'Transaction']">
            @forelse ($agreements as $agreement)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('staff.commissions.show', $agreement) }}">{{ $agreement->reference }}</a></td>
                    <td class="px-4 py-4 text-sm">{{ $agreement->status->label() }}</td>
                    <td class="px-4 py-4 text-sm">{{ $agreement->broker->name }}</td>
                    <td class="px-4 py-4 text-sm">{{ $agreement->transaction->reference }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8"><x-workspace.empty title="No agreements" message="Propose an agreement from a sales transaction." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
