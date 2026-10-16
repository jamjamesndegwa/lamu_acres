<x-workspace-layout heading="Notifications">
    <div class="mt-3 flex flex-wrap items-center justify-between gap-4">
        <p class="max-w-2xl text-sm text-stone-600">Updates from all your workspaces. Needs attention shows work that is still pending, even after you read its notification.</p>
        <form method="POST" action="{{ route($readRoute) }}">@csrf<button type="submit" class="rounded-lg border border-sand-200 bg-white px-4 py-2 text-sm font-medium text-lamu-900 hover:bg-sand-50">Mark all as read</button></form>
    </div>
    <nav aria-label="Filter notifications" class="my-5 flex flex-wrap gap-2">
        @foreach (['all' => 'All', 'unread' => 'Unread', 'attention' => 'Needs attention'] as $value => $label)
            <a href="{{ route($indexRoute, ['filter' => $value]) }}" @if ($filter === $value) aria-current="page" @endif class="rounded-full border px-4 py-2 text-sm font-medium {{ $filter === $value ? 'border-lamu-900 bg-lamu-900 text-white' : 'border-sand-200 bg-white text-lamu-900' }}">{{ $label }}</a>
        @endforeach
    </nav>
    <div class="divide-y divide-sand-200 overflow-hidden rounded-xl border border-sand-200 bg-white">
        @forelse ($notifications as $notification)
            <a href="{{ route($openRoute, $notification->id) }}" class="block p-4 hover:bg-sand-50 {{ $notification->read_at === null ? 'border-l-4 border-l-lamu-700' : '' }}">
                <div class="flex flex-wrap items-center gap-2"><span class="font-semibold text-lamu-900">{{ $notification->data['title'] ?? 'Update' }}</span>@if ($notification->read_at === null)<span class="text-xs text-lamu-700">Unread</span>@endif<span class="rounded-full bg-sand-100 px-2 py-1 text-xs text-stone-700">{{ in_array($notification->id, $attentionIds, true) ? 'Needs attention' : (str_contains($notification->type, 'Response') ? 'Message' : 'Update') }}</span></div>
                <p class="mt-1 text-sm text-stone-600">{{ $notification->data['message'] ?? '' }}</p>
                <time class="mt-2 block text-xs text-stone-500" datetime="{{ $notification->created_at?->toIso8601String() }}" title="{{ $notification->created_at?->toDayDateTimeString() }}">{{ $notification->created_at?->diffForHumans() }}</time>
            </a>
        @empty
            <div class="p-8"><x-workspace.empty title="No notifications here" message="Updates about your account and activity will appear here. Try All to see your history." /></div>
        @endforelse
    </div>
    <div class="mt-4"><x-workspace.pagination :paginator="$notifications" /></div>
</x-workspace-layout>
