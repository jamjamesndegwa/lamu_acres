<x-workspace-layout heading="Verification queue">
    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($summary as $row)
            <a href="{{ route('staff.verification.'.$row['key']) }}" class="rounded-2xl border border-sand-200 bg-white p-5">
                <p class="text-sm text-stone-500">{{ $row['label'] }}</p>
                <p class="mt-2 text-2xl font-semibold text-lamu-900">{{ $row['count'] }}</p>
            </a>
        @endforeach
    </div>
    <div class="mt-6">
        <x-workspace.table :columns="['Property', 'Level', 'Override']">
            @forelse ($properties as $property)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4"><a class="font-medium text-lamu-900 hover:underline" href="{{ route('staff.verification.show', $property) }}">{{ $property->title }}</a></td>
                    <td class="px-4 py-4 text-sm">{{ $property->passport?->level->label() ?? 'Level 0 — Advertised' }}</td>
                    <td class="px-4 py-4 text-sm">{{ $property->passport?->override->label() ?? 'None' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="px-4 py-8"><x-workspace.empty title="No properties" message="Submitted land will appear here." /></td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
