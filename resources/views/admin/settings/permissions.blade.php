<x-workspace-layout heading="Permissions">
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-3xl text-sm text-stone-600">
            Read-only catalogue of permission keys. Assign permissions to people through roles on the
            <a href="{{ route('admin.settings.roles') }}" class="font-semibold text-lamu-800 underline">roles</a>
            screen or when editing a user account.
        </p>
        <a href="{{ route('admin.settings.index') }}" class="rounded-full border border-sand-200 bg-white px-4 py-2 text-sm font-medium text-lamu-900 hover:bg-lamu-100">
            Back to settings
        </a>
    </div>

    <div class="mt-8 space-y-8">
        @foreach ($groups as $group => $permissions)
            <section class="rounded-2xl border border-sand-200 bg-white shadow-sm">
                <header class="border-b border-sand-200 px-5 py-4">
                    <h2 class="font-serif text-xl text-lamu-900">{{ Str::headline((string) $group) }}</h2>
                </header>
                <x-workspace.table :columns="['Permission', 'Key']">
                    @foreach ($permissions as $permission)
                        <tr class="border-b border-sand-200 last:border-b-0">
                            <th scope="row" class="px-4 py-3 text-left font-medium text-lamu-900">{{ $permission['label'] }}</th>
                            <td class="px-4 py-3 font-mono text-xs text-stone-600">{{ $permission['name'] }}</td>
                        </tr>
                    @endforeach
                </x-workspace.table>
            </section>
        @endforeach
    </div>
</x-workspace-layout>
