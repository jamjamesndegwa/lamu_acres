<x-workspace-layout heading="Notifications">
    <p class="mt-3 max-w-3xl text-stone-700">Updates about your listings and referrals. Opening one marks it as read.</p>
    <div class="mt-6">
        <x-workspace.table :columns="['Update', 'When']">
            @forelse ($notifications as $notification)
                <tr class="border-b border-sand-200 last:border-b-0">
                    <td class="px-4 py-4">
                        <a href="{{ route($openRoute, $notification->id) }}" class="font-medium text-lamu-900 hover:underline">
                            {{ $notification->data['title'] ?? 'Update' }}
                        </a>
                        @if ($notification->read_at === null)
                            <span class="ml-2 text-xs font-medium text-lamu-800">Unread</span>
                        @endif
                        @if (! empty($notification->data['message']))
                            <p class="mt-1 text-stone-600">{{ $notification->data['message'] }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-4 text-stone-600">{{ $notification->created_at?->toDayDateTimeString() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="px-4 py-8">
                        <x-workspace.empty title="No notifications" message="Staff questions, listing decisions, and referral reviews will appear here." />
                    </td>
                </tr>
            @endforelse
        </x-workspace.table>
        <div class="mt-4"><x-workspace.pagination :paginator="$notifications" /></div>
    </div>
</x-workspace-layout>
