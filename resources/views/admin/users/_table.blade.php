<turbo-frame id="users" class="block">
    <x-workspace.table :columns="['Name', 'Email', 'Roles', '']">
        @forelse ($users as $account)
            <tr class="border-b border-sand-200 align-top last:border-b-0">
                <td class="px-4 py-4 font-medium text-lamu-900">{{ $account->name }}</td>
                <td class="px-4 py-4">{{ $account->email }}</td>
                <td class="px-4 py-4 text-stone-600">
                    {{ $account->roles->pluck('label')->filter()->implode(', ') ?: 'No roles' }}
                </td>
                <td class="px-4 py-4 text-right">
                    @if (auth()->id() !== $account->id)
                        <a
                            href="{{ route('admin.users.roles.edit', $account) }}"
                            data-turbo-frame="modal"
                            class="rounded-full bg-lamu-900 px-4 py-2 text-xs font-medium text-white"
                        >
                            Edit roles
                        </a>
                        <a
                            href="{{ route('admin.users.suspension.edit', $account) }}"
                            data-turbo-frame="modal"
                            class="ml-2 rounded-full border border-sand-200 px-4 py-2 text-xs font-medium text-lamu-900"
                        >
                            {{ $account->suspended_at ? 'Restore' : 'Suspend' }}
                        </a>
                    @else
                        <span class="text-xs text-stone-400">Current account</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="px-4 py-8">
                    <x-workspace.empty title="No accounts" message="No user accounts are available to review." />
                </td>
            </tr>
        @endforelse
    </x-workspace.table>

    <div class="mt-4">
        <x-workspace.pagination :paginator="$users" frame="users" />
    </div>
</turbo-frame>
