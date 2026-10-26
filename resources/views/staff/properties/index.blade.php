<x-workspace-layout heading="Property register">
    <div class="mt-4 flex flex-wrap items-end justify-between gap-3">
        <x-filter-bar class="grid flex-1 gap-2 sm:grid-cols-4">
            <input name="q" value="{{ request('q') }}" placeholder="Title or reference" class="rounded-lg border border-sand-200 px-3 py-2">
            <select name="status" class="rounded-lg border border-sand-200 px-3 py-2">
                <option value="">Any status</option>
                @foreach (\App\Enums\PropertyStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <select name="county_id" class="rounded-lg border border-sand-200 px-3 py-2">
                <option value="">Any county</option>
                @foreach ($counties as $county)
                    <option value="{{ $county->id }}" @selected((string) request('county_id') === (string) $county->id)>{{ $county->name }}</option>
                @endforeach
            </select>
            <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
        </x-filter-bar>
        <a href="{{ route('staff.properties.create') }}" data-turbo-frame="_top" class="rounded-full border border-lamu-900 px-4 py-2 text-sm font-medium text-lamu-900">Add property</a>
    </div>

    <div class="mt-6">
        <x-workspace.table :columns="['Reference', 'Property', 'Status', 'Offers', 'Acreage left', 'Price per acre', '']">
            @forelse ($properties as $property)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4 text-sm">{{ $property->reference }}</td>
                    <td class="px-4 py-4">
                        <a href="{{ route('staff.properties.show', $property) }}" class="font-medium text-lamu-900 hover:underline">{{ $property->title }}</a>
                        <span class="mt-1 block text-xs text-stone-500">{{ $property->locationLabel() }}</span>
                    </td>
                    <td class="px-4 py-4 text-sm"><x-status-badge :label="$property->status->label()" /></td>
                    <td class="px-4 py-4 text-sm">
                        @if ($property->offers_count > 0)
                            <a href="{{ route('staff.properties.show', [$property, 'tab' => 'offers']) }}" class="font-medium text-lamu-800 hover:underline">{{ $property->offers_count }} {{ \Illuminate\Support\Str::plural('offer', $property->offers_count) }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-4 text-sm">{{ number_format($property->remainingAcreage(), 2) }}</td>
                    <td class="px-4 py-4 text-sm">{{ $property->pricePerAcre() ? 'KSh '.number_format($property->pricePerAcre()) : '—' }}</td>
                    <td class="px-4 py-4 text-right text-sm">
                        <a href="{{ route('staff.properties.show', [$property, 'dialog' => 'status']) }}" data-turbo-frame="modal" class="font-medium text-lamu-800">Status</a>
                        <a href="{{ route('staff.properties.show', [$property, 'dialog' => 'portion']) }}" data-turbo-frame="modal" class="ml-3 font-medium text-lamu-800">Portion</a>
                        <a href="{{ route('staff.properties.edit', $property) }}" data-turbo-frame="modal" class="ml-3 font-medium text-lamu-800">Edit</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8">
                        <x-workspace.empty title="No properties" message="Published inventory is empty until a property is recorded here." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4">{{ $properties->links() }}</div>
    </div>
</x-workspace-layout>
