<?php

namespace App\Services\Access;

use App\Enums\Workspace;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class NavigationBuilder
{
    /**
     * @return array{heading: string, intro: string}
     */
    public function header(User $user, Workspace $workspace): array
    {
        return [
            'heading' => $this->heading($user, $workspace),
            'intro' => (string) config("workspaces.{$workspace->value}.intro", ''),
        ];
    }

    /**
     * Sidebar modules, the open module's horizontal items, and account actions.
     *
     * @return array{
     *     modules: array<int, array{key: string, label: string, href: ?string, active: bool}>,
     *     moduleLabel: string,
     *     subnav: array<int, array{label: string, href: ?string, method: string, active: bool, available: bool, frame: ?string}>,
     *     account: array<int, array{label: string, href: ?string, method: string, active: bool, available: bool, frame: ?string}>
     * }
     */
    public function shell(User $user, Workspace $workspace): array
    {
        $currentRoute = session('workspace_route');
        $modules = [];

        foreach (config("workspaces.{$workspace->value}.groups", []) as $group) {
            if (! $this->allowed($user, $group['permission'] ?? null)) {
                continue;
            }

            $items = $this->present($group['items'] ?? [], $currentRoute);

            if ($items === []) {
                continue;
            }

            $entry = collect($items)->first(
                fn (array $item): bool => $item['available'] && $item['method'] === 'get' && $item['frame'] === null,
            );
            $href = is_array($entry) ? $entry['href'] : null;

            $modules[] = [
                'key' => (string) ($group['key'] ?? Str::slug((string) ($group['label'] ?? 'module'))),
                'label' => (string) ($group['label'] ?? $items[0]['label']),
                'href' => $href,
                'items' => $items,
            ];
        }

        $activeKey = $this->activeModuleKey($modules, $workspace);

        $modules = array_map(function (array $module) use ($activeKey): array {
            $module['active'] = $module['key'] === $activeKey;

            return $module;
        }, $modules);

        $active = collect($modules)->firstWhere('active', true);

        return [
            'modules' => array_map(fn (array $module): array => [
                'key' => $module['key'],
                'label' => $module['label'],
                'href' => $module['href'],
                'active' => $module['active'],
            ], $modules),
            'moduleLabel' => is_array($active) ? $active['label'] : '',
            'subnav' => is_array($active) ? $active['items'] : [],
            'account' => $this->present(config("workspaces.{$workspace->value}.account", []), $currentRoute),
        ];
    }

    /**
     * @return array<int, array{title: string, description: string, href: ?string}>
     */
    public function panels(User $user, Workspace $workspace): array
    {
        return collect(config("workspaces.{$workspace->value}.panels", []))
            ->filter(fn (array $panel): bool => $this->allowed($user, $panel['permission'] ?? null))
            ->map(function (array $panel): array {
                $route = $panel['route'] ?? null;

                return [
                    'title' => $panel['title'],
                    'description' => $panel['description'],
                    'href' => is_string($route) && Route::has($route) ? route($route) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{key: string, label: string, href: ?string, items: array<int, array{active: bool}>}>  $modules
     */
    private function activeModuleKey(array $modules, Workspace $workspace): ?string
    {
        $pin = session('workspace_module_pin');

        if (
            is_array($pin)
            && ($pin['workspace'] ?? null) === $workspace->value
            && collect($modules)->contains('key', $pin['key'] ?? null)
        ) {
            return $pin['key'];
        }

        foreach ($modules as $module) {
            foreach ($module['items'] as $item) {
                if ($item['active']) {
                    return $module['key'];
                }
            }
        }

        return null;
    }

    private function heading(User $user, Workspace $workspace): string
    {
        $fallback = (string) config("workspaces.{$workspace->value}.heading", 'Dashboard');

        if ($workspace !== Workspace::Staff) {
            return $fallback;
        }

        $desks = [
            'staff.properties' => 'Property desk',
            'staff.buyers' => 'Buyer desk',
            'staff.sourcing' => 'Sourcing desk',
            'staff.brokerage' => 'Broker desk',
            'staff.sales' => 'Sales desk',
            'staff.verification' => 'Verification desk',
            'staff.intelligence' => 'Intelligence desk',
            'staff.finance' => 'Finance desk',
            'staff.communications' => 'Communications desk',
        ];

        $matched = collect($desks)
            ->filter(fn (string $heading, string $permission): bool => $user->hasPermission($permission))
            ->values();

        return match ($matched->count()) {
            0 => $fallback,
            1 => (string) $matched->first(),
            default => 'Operations',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{label: string, href: ?string, method: string, active: bool, available: bool, frame: ?string}>
     */
    private function present(array $items, mixed $currentRoute): array
    {
        return collect($items)
            ->map(fn (array $item): array => $this->item($item, $currentRoute))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{label: string, href: ?string, method: string, active: bool, available: bool, frame: ?string}
     */
    private function item(array $item, mixed $currentRoute): array
    {
        $route = $item['route'] ?? null;
        $href = is_string($route) && Route::has($route) ? route($route) : null;
        $pattern = $item['active'] ?? $route;

        return [
            'label' => $item['label'],
            'href' => $href,
            'method' => $item['method'] ?? 'get',
            'active' => is_string($href) && is_string($currentRoute) && is_string($pattern)
                && Str::is($pattern, $currentRoute),
            'available' => $href !== null,
            'frame' => $item['frame'] ?? (is_string($route) && str_ends_with($route, '.profile') ? 'modal' : null),
        ];
    }

    private function allowed(User $user, mixed $permission): bool
    {
        return ! is_string($permission) || $user->hasPermission($permission);
    }
}
