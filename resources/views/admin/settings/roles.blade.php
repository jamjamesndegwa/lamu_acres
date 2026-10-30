<x-workspace-layout heading="Roles">
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-3xl text-sm text-stone-600">
            Each role bundles permissions for a workspace or desk. Change who holds a role from the
            <a href="{{ route('admin.users.index') }}" class="font-semibold text-lamu-800 underline">user directory</a>.
        </p>
        <a href="{{ route('admin.settings.index') }}" class="rounded-full border border-sand-200 bg-white px-4 py-2 text-sm font-medium text-lamu-900 hover:bg-lamu-100">
            Back to settings
        </a>
    </div>

    <div class="mt-8 space-y-6">
        @foreach ($roles as $role)
            <section class="rounded-2xl border border-sand-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-serif text-xl text-lamu-900">{{ $role['label'] }}</h2>
                        <p class="mt-1 font-mono text-xs text-stone-500">{{ $role['name'] }}</p>
                        @if ($role['workspace'])
                            <p class="mt-2 text-sm text-stone-600">Primary workspace: {{ Str::headline($role['workspace']) }}</p>
                        @endif
                        @if ($role['description'] !== '')
                            <p class="mt-2 max-w-3xl text-sm text-stone-700">{{ $role['description'] }}</p>
                        @endif
                    </div>
                    <span class="rounded-full bg-lamu-100 px-3 py-1 text-xs font-semibold text-lamu-900">
                        {{ count($role['permissions']) }} permissions
                    </span>
                </div>
                @if ($role['permissions'] !== [])
                    <ul class="mt-4 flex flex-wrap gap-2">
                        @foreach ($role['permissions'] as $permission)
                            <li class="rounded-full border border-sand-200 bg-sand-50 px-3 py-1 text-xs text-stone-700" title="{{ $permission }}">
                                {{ $permissionLabels[$permission] ?? $permission }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-4 text-sm text-stone-600">No permissions are assigned to this role yet.</p>
                @endif
            </section>
        @endforeach
    </div>
</x-workspace-layout>
