<?php

namespace App\Http\Controllers;

use App\Services\Communications\NotificationInbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceNotificationController extends Controller
{
    public function index(Request $request, NotificationInbox $inbox): View
    {
        $data = $request->validate(['filter' => ['nullable', Rule::in(['all', 'unread', 'attention'])]]);
        $filter = $data['filter'] ?? 'all';
        $query = $request->user()->notifications()->getQuery();
        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'attention') {
            $inbox->attention($query, $request->user());
        }
        $prefix = $this->prefix($request);
        $notifications = $query->latest()->paginate(20)->withQueryString();

        return view('workspace.notifications.index', [
            'notifications' => $notifications,
            'attentionIds' => $inbox->attention($request->user()->notifications()->getQuery(), $request->user())->whereKey($notifications->getCollection()->modelKeys())->pluck('id')->all(),
            'openRoute' => $prefix.'.notifications.open',
            'indexRoute' => $prefix.'.notifications.index',
            'readRoute' => $prefix.'.notifications.read-all',
            'filter' => $filter,
            'inbox' => $inbox,
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route($this->prefix($request).'.notifications.index')->with('status', 'All your notifications are marked as read.');
    }

    public function open(Request $request, string $notification, NotificationInbox $inbox): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();
        $path = $inbox->destination($item, $request->user());

        return $path === null
            ? redirect()->route($this->prefix($request).'.notifications.index')->with('status', 'This update is saved, but its record is no longer available to your account.')
            : redirect()->to($path);
    }

    private function prefix(Request $request): string
    {
        return strstr((string) $request->route()->getName(), '.', true);
    }
}
