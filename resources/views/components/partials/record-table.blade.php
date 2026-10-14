<x-workspace.table :labelled-by="'table-'.\Illuminate\Support\Str::slug($table['heading'])" :columns="$table['columns']">
    @forelse ($table['rows'] as $row)
        <tr class="border-b border-sand-200 last:border-b-0">
            @foreach ($row['cells'] as $index => $cell)
                <td class="px-4 py-3 text-sm text-lamu-900">
                    @if ($index === 0 && filled($row['href']))
                        <a href="{{ $row['href'] }}" class="font-medium hover:underline">{{ $cell }}</a>
                    @else
                        {{ $cell }}
                    @endif
                </td>
            @endforeach
        </tr>
    @empty
        <tr>
            <td colspan="{{ count($table['columns']) }}" class="px-4 py-8">
                <x-workspace.empty :message="$empty" />
            </td>
        </tr>
    @endforelse
</x-workspace.table>
