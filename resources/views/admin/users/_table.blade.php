<turbo-frame id="users" class="block">
    <x-workspace.table :columns="['Name', 'Email', 'Roles', 'Login', '']">
        @forelse ($users as $account)
            <tr class="border-b border-sand-200 align-top last:border-b-0">
                <td class="px-4 py-4 font-medium text-lamu-900">
                    <a href="{{ route('admin.users.show', $account) }}" class="hover:underline">{{ $account->name }}</a>
                </td>
                <td class="px-4 py-4">{{ $account->email }}</td>
                <td class="px-4 py-4 text-stone-600">
                    {{ $account->roles->pluck('label')->filter()->implode(', ') ?: 'No roles' }}
                </td>
                <td class="px-4 py-4">
                    @if ($account->isSuspended())
                        <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-900">Suspended</span>
                    @else
                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-900">Active</span>
                    @endif
                </td>
                <td class="px-4 py-4 text-right">
                    <a
                        href="{{ route('admin.users.show', $account) }}"
                        class="rounded-full bg-lamu-900 px-4 py-2 text-xs font-medium text-white"
                    >
                        Manage
                    </a>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="px-4 py-8">
                    <x-workspace.empty title="No accounts" message="No user accounts are available to review." />
                </td>
            </tr>
        @endforelse
    </x-workspace.table>

    <div class="mt-4">
        <x-workspace.pagination :paginator="$users" frame="users" />
    </div>
</turbo-frame>
