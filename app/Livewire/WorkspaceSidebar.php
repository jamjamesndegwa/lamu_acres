<?php

namespace App\Livewire;

use App\Enums\Workspace;
use App\Services\Access\NavigationBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class WorkspaceSidebar extends Component
{
    #[On('refresh-workspace-sidebar')]
    public function refreshNavigation(): void {}

    public function select(string $key): void
    {
        $workspace = Workspace::tryFrom((string) session('workspace'));
        $user = auth()->user();

        if ($workspace === null || $user === null || $key === '') {
            return;
        }

        $modules = collect(app(NavigationBuilder::class)->shell($user, $workspace)['modules']);
        $module = $modules->firstWhere('key', $key);

        if (! is_array($module)) {
            return;
        }

        if (is_string($module['href']) && $module['href'] !== '') {
            $this->redirect($module['href']);

            return;
        }

        session()->put('workspace_module_pin', [
            'workspace' => $workspace->value,
            'key' => $key,
        ]);

        $this->dispatch('workspace-module-changed');
    }

    public function render(NavigationBuilder $navigation): View
    {
        $workspace = Workspace::tryFrom((string) session('workspace'));
        $user = auth()->user();
        $shell = $workspace && $user ? $navigation->shell($user, $workspace) : [
            'modules' => [],
            'account' => [],
        ];

        return view('livewire.workspace-sidebar', [
            'workspace' => $workspace,
            'eyebrow' => $workspace ? config("workspaces.{$workspace->value}.eyebrow") : null,
            'modules' => $shell['modules'],
            'account' => $shell['account'],
        ]);
    }
}
