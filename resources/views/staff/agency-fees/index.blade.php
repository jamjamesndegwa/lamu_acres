<x-workspace-layout heading="Agency fee agreements">
    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Sale', 'Seller', 'Status', 'Earned']">
            @forelse ($agreements as $agreement)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('staff.agency-fees.show', $agreement) }}">{{ $agreement->reference }}</a></td>
                    <td class="px-4 py-4 text-sm">{{ $agreement->transaction?->reference }}</td>
                    <td class="px-4 py-4 text-sm">{{ $agreement->seller?->name }}</td>
                    <td class="px-4 py-4 text-sm">{{ $agreement->status->label() }}</td>
                    <td class="px-4 py-4 text-sm">@if ($agreement->earned_amount) KSh {{ number_format($agreement->earned_amount) }} @else — @endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8"><x-workspace.empty title="No agency-fee agreements" message="Propose a fee from the sales desk before completion." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
