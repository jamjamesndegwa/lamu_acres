<x-workspace-layout heading="Satellite and aerial imagery">
    <x-editorial-workflow />
    <p class="mt-3 max-w-3xl text-stone-700">Upload only imagery you are allowed to hold. A source URL is stored as a citation and is not downloaded. Images stay hidden when redistribution is not allowed.</p>
    <div class="mt-4 flex gap-2">
        <a href="{{ route('staff.imagery.scenes.create') }}" class="rounded-full bg-lamu-900 px-4 py-2 text-sm text-white">New scene</a>
        <a href="{{ route('staff.imagery.comparisons.create') }}" class="rounded-full border border-lamu-900 px-4 py-2 text-sm">New comparison</a>
    </div>
    <h2 class="mt-8 font-semibold">Scenes</h2>
    <div class="mt-3">
        <x-workspace.table :columns="['Scene', 'Acquired', 'Licence', 'Publication']">
            @forelse ($scenes as $scene)
                <tr class="border-b border-sand-200">
                    <td class="px-4 py-3"><a class="text-lamu-800 hover:underline" href="{{ route('staff.imagery.scenes.edit', $scene) }}">{{ $scene->provider }} · {{ $scene->coverage }}</a></td>
                    <td class="px-4 py-3">{{ $scene->acquired_on->toFormattedDateString() }}</td>
                    <td class="px-4 py-3">{{ $scene->license_name }} · {{ $scene->redistribution_allowed ? 'redistribution allowed' : 'not for redistribution' }}</td>
                    <td class="px-4 py-3">{{ $scene->publication_status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-6 text-sm text-stone-600">No scenes.</td></tr>
            @endforelse
        </x-workspace.table>
    </div>
    <h2 class="mt-8 font-semibold">Comparisons</h2>
    <div class="mt-3">
        <x-workspace.table :columns="['Area', 'Publication']">
            @forelse ($comparisons as $comparison)
                <tr class="border-b border-sand-200">
                    <td class="px-4 py-3"><a class="text-lamu-800 hover:underline" href="{{ route('staff.imagery.comparisons.show', $comparison) }}">{{ $comparison->area_label }}</a></td>
                    <td class="px-4 py-3">{{ $comparison->publication_status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="px-4 py-6 text-sm text-stone-600">No comparisons.</td></tr>
            @endforelse
        </x-workspace.table>
    </div>
</x-workspace-layout>
