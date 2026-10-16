@props(['workspace' => null, 'count' => 0])

@if ($workspace && auth()->check() && Route::has($workspace->value.'.notifications.index'))
    <style>
        .notification-popover { position: fixed; inset: 5rem .75rem auto; z-index: 60; max-height: min(75dvh, calc(100dvh - 6rem)); overflow-y: auto; }
        @media (min-width: 640px) { .notification-popover { position: absolute; inset: calc(100% + .75rem) 0 auto auto; width: min(24rem, calc(100vw - 1.5rem)); } }
    </style>
    @php
        $recent = auth()->user()->notifications()->latest()->limit(7)->get();
        $inbox = app(\App\Services\Communications\NotificationInbox::class);
        $attentionIds = $inbox->attention(auth()->user()->notifications()->getQuery(), auth()->user())->whereKey($recent->modelKeys())->pluck('id')->all();
    @endphp
    <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.stop="open = false; $refs.bell.focus()">
        <button type="button" x-ref="bell" @click="open = !open" :aria-expanded="open" aria-controls="notification-dropdown" aria-label="Notifications, {{ (int) $count }} unread" class="relative flex size-11 shrink-0 items-center justify-center rounded-full {{ (int) $count > 0 ? 'text-red-700' : 'text-lamu-900' }} hover:bg-sand-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">
            <svg aria-hidden="true" class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
            @if ((int) $count > 0)
                <span data-unread-badge class="absolute -left-1 -top-1 min-w-5 rounded-full bg-red-700 px-1.5 py-0.5 text-center text-[10px] font-bold leading-4 text-white ring-2 ring-white" aria-hidden="true">{{ (int) $count > 99 ? '99+' : (int) $count }}</span>
            @endif
        </button>
        <section id="notification-dropdown" x-show="open" style="display: none" x-transition.opacity aria-label="Recent notifications" class="notification-popover rounded-xl border border-sand-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-sand-200 px-4 py-3"><h2 class="font-semibold text-lamu-900">Notifications</h2><a href="{{ route($workspace->value.'.notifications.index') }}" class="text-sm font-medium text-lamu-800 underline">View all</a></div>
            <ul class="divide-y divide-sand-200">
                @forelse ($recent as $notice)
                    <li><a href="{{ route($workspace->value.'.notifications.open', $notice->id) }}" class="block px-4 py-3 hover:bg-sand-50 focus-visible:outline-2 focus-visible:outline-lamu-700 {{ $notice->read_at === null ? 'bg-sand-50' : '' }}">
                        <span class="flex items-start justify-between gap-2"><span class="text-sm font-semibold text-lamu-900">{{ $notice->data['title'] ?? 'Update' }}</span>@if ($notice->read_at === null)<span class="mt-1 size-2 shrink-0 rounded-full bg-lamu-700"><span class="sr-only">Unread</span></span>@endif</span>
                        <span class="mt-1 block line-clamp-2 text-sm text-stone-600">{{ \Illuminate\Support\Str::limit($notice->data['message'] ?? '', 160) }}</span>
                        <span class="mt-2 flex justify-between gap-2 text-xs text-stone-500"><time datetime="{{ $notice->created_at?->toIso8601String() }}">{{ $notice->created_at?->diffForHumans() }}</time><span>{{ in_array($notice->id, $attentionIds, true) ? 'Needs attention' : (str_contains($notice->type, 'Response') ? 'Message' : 'Update') }}</span></span>
                    </a></li>
                @empty
                    <li class="px-4 py-8 text-sm text-stone-600">You’re all caught up. Updates about your applications and activity will appear here.</li>
                @endforelse
            </ul>
        </section>
    </div>
@endif
