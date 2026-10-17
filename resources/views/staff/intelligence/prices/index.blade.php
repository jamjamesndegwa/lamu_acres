<x-workspace-layout heading="Land price observatory">
    <x-editorial-workflow />
    <p class="mt-3 max-w-3xl text-stone-700">Medians use published asking prices only. A verified sale is stored separately and is not treated as an asking price.</p>
    @forelse ($rows as $row)
        <article class="mt-4 rounded-2xl border border-sand-200 bg-white p-4">
            <h2 class="font-semibold">{{ $row['locality']->name }}</h2>
            <p class="text-sm">Sample {{ $row['sample'] }} · median asking KSh {{ number_format($row['median']) }} per acre</p>
        </article>
    @empty
        <p class="mt-4 text-sm text-stone-600">No published asking prices yet. No median has been calculated.</p>
    @endforelse
    <h2 class="mt-8 font-semibold">Record a verified sale</h2>
    <form method="POST" action="{{ route('staff.prices.sales.store') }}" enctype="multipart/form-data" class="mt-3 grid max-w-3xl gap-3 sm:grid-cols-2">
        @csrf
        <select name="locality_id" class="rounded-lg border border-sand-200 px-3 py-2"><option value="">Locality</option>@foreach ($localities as $locality)<option value="{{ $locality->id }}">{{ $locality->name }}</option>@endforeach</select>
        <input name="acreage" placeholder="Acres" required class="rounded-lg border border-sand-200 px-3 py-2">
        <input name="price_amount" placeholder="Sale price KSh" required class="rounded-lg border border-sand-200 px-3 py-2">
        <input type="date" name="sold_on" required class="rounded-lg border border-sand-200 px-3 py-2">
        <input name="source_name" placeholder="Source" required class="rounded-lg border border-sand-200 px-3 py-2">
        <x-upload-manager name="evidence" label="Sale evidence" required container-class="sm:col-span-2" />
        <button class="rounded-full bg-lamu-900 px-4 py-2 text-sm text-white">Save verified sale</button>
    </form>
    <div class="mt-6">
        <x-workspace.table :columns="['Sale', 'Publication', '']">
            @foreach ($sales as $sale)
                <tr class="border-b border-sand-200">
                    <td class="px-4 py-3">{{ $sale->reference }} · KSh {{ number_format($sale->price_amount) }}</td>
                    <td class="px-4 py-3">{{ $sale->publication_status->label() }}</td>
                    <td class="px-4 py-3">
                        @if (in_array($sale->publication_status, [\App\Enums\PublicationStatus::Draft, \App\Enums\PublicationStatus::Withdrawn], true))
                            <form method="POST" action="{{ route('staff.prices.sales.submit', $sale) }}">@csrf <button class="text-sm text-lamu-800">Submit</button></form>
                        @endif
                        @if ($sale->publication_status === \App\Enums\PublicationStatus::Review && auth()->user()->hasPermission('intelligence.publish'))
                            <form method="POST" action="{{ route('staff.prices.sales.publish', $sale) }}">@csrf <button class="text-sm text-lamu-800">Publish</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-workspace.table>
    </div>
</x-workspace-layout>
