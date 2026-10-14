<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceNotificationController extends Controller
{
    public function index(Request $request): View
    {
        $name = (string) $request->route()?->getName();
        $prefix = strstr($name, '.', true);
        $prefix = in_array($prefix, ['buyer', 'broker', 'seller', 'investor', 'staff', 'admin'], true) ? $prefix : 'buyer';

        return view('workspace.notifications.index', [
            'notifications' => $request->user()->notifications()->latest()->paginate(20),
            'openRoute' => $prefix.'.notifications.open',
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        $path = $this->safePath($item->data['url'] ?? null);

        return $path === null ? back() : redirect()->to($path);
    }

    private function safePath(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts)) {
            return null;
        }

        $path = $parts['path'] ?? null;

        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        $host = $parts['host'] ?? null;
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($host) && is_string($appHost) && strcasecmp($host, $appHost) !== 0) {
            return null;
        }

        $query = isset($parts['query']) ? '?'.$parts['query'] : '';

        return $path.$query;
    }
}
