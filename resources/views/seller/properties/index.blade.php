<x-workspace-layout heading="My properties">
    <p class="mt-3 max-w-3xl text-stone-700">Land you have submitted, and land staff have verified you can market. Staff check ownership and authority before anything is published.</p>
    <a href="{{ route('seller.properties.create') }}" class="mt-4 inline-block rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">List land for sale or lease</a>

    @foreach ($groups as $key => $group)
        <section id="{{ $key }}" class="mt-8">
            <h2 class="font-semibold text-lamu-900">{{ $group['title'] }}</h2>
            <div class="mt-3">
                <x-workspace.table :columns="['Property', 'Reference', 'Purpose', 'Price', 'Offers', 'Status']">
                    @forelse ($group['items'] as $property)
                        <tr class="border-b border-sand-200 last:border-b-0">
                            <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('seller.properties.show', $property) }}">{{ $property->title }}</a></td>
                            <td class="px-4 py-4 text-sm">{{ $property->reference }}</td>
                            <td class="px-4 py-4 text-sm">{{ $property->purpose?->label() ?? 'Not set' }}</td>
                            <td class="px-4 py-4 text-sm">{{ $property->formattedPrice() ?? 'Not set' }}</td>
                            <td class="px-4 py-4 text-sm">
                                @if ($property->offers_count > 0)
                                    <a href="{{ route('seller.properties.show', $property) }}#listing-offers" class="font-medium text-lamu-800 hover:underline">{{ $property->offers_count }} {{ \Illuminate\Support\Str::plural('offer', $property->offers_count) }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm"><x-status-badge :label="$property->has_open_request ? 'Information requested' : $property->status->label()" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-sm text-stone-600">{{ $group['empty'] }}</td></tr>
                    @endforelse
                </x-workspace.table>
            </div>
        </section>
    @endforeach
</x-workspace-layout>
