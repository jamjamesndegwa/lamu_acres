<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSettingsAccess($request);

        $actor = $request->user();
        $cards = collect([
            [
                'title' => 'Permissions',
                'description' => 'Reference for workspace, desk, finance, and administration permissions used across Lamu Acres.',
                'route' => 'admin.settings.permissions',
                'icon' => 'fa-shield-halved',
                'allowed' => $actor?->hasPermission('admin.users') === true,
            ],
            [
                'title' => 'Roles',
                'description' => 'Buyer, broker, staff, and administrator roles with the permissions each role carries.',
                'route' => 'admin.settings.roles',
                'icon' => 'fa-user-gear',
                'allowed' => $actor?->hasPermission('admin.users') === true,
            ],
            [
                'title' => 'Commercial settings',
                'description' => 'Investor Pass plans, commission policy, listing rules, and verification standards.',
                'route' => 'admin.plans.edit',
                'icon' => 'fa-tags',
                'allowed' => $actor?->hasPermission('admin.commercial') === true,
                'modal' => false,
            ],
            [
                'title' => 'System operations',
                'description' => 'Application health, queues, schedules, integrations, and recent audit activity.',
                'route' => 'admin.operations.index',
                'icon' => 'fa-gear',
                'allowed' => $actor?->hasPermission('admin.settings') === true,
                'modal' => false,
            ],
        ])
            ->filter(fn (array $card): bool => $card['allowed'])
            ->map(function (array $card): array {
                $route = $card['route'];
                unset($card['allowed']);

                $card['href'] = Route::has($route) ? route($route) : null;
                $card['frame'] = ($card['modal'] ?? true) === false ? null : null;

                return $card;
            })
            ->values()
            ->all();

        return view('admin.settings.index', [
            'cards' => $cards,
        ]);
    }

    public function permissions(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('admin.users'), 403);

        $catalog = collect(config('access.permissions', []))
            ->map(fn (array $meta, string $name): array => [
                'name' => $name,
                'label' => $meta['label'] ?? $name,
                'group' => $meta['group'] ?? 'other',
            ])
            ->groupBy('group')
            ->sortKeys();

        return view('admin.settings.permissions', [
            'groups' => $catalog,
        ]);
    }

    public function roles(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('admin.users'), 403);

        $configured = collect(config('access.roles', []));
        $roles = Role::query()
            ->with('permissions')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) use ($configured): array {
                $config = $configured->get($role->name, []);

                return [
                    'name' => $role->name,
                    'label' => $role->label ?: ($config['label'] ?? $role->name),
                    'workspace' => $role->workspace ?: ($config['workspace'] ?? null),
                    'description' => $role->description ?: ($config['description'] ?? ''),
                    'permissions' => $role->permissions->pluck('name')->sort()->values()->all(),
                ];
            });

        $permissionLabels = collect(config('access.permissions', []))
            ->mapWithKeys(fn (array $meta, string $name): array => [$name => $meta['label'] ?? $name]);

        return view('admin.settings.roles', [
            'roles' => $roles,
            'permissionLabels' => $permissionLabels,
        ]);
    }

    private function authorizeSettingsAccess(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user?->hasPermission('admin.settings') || $user?->hasPermission('admin.users'),
            403,
        );
    }
}
