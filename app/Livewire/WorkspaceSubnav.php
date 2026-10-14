<?php

namespace App\Livewire;

use App\Enums\Workspace;
use App\Services\Access\NavigationBuilder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class WorkspaceSubnav extends Component
{
    #[On('workspace-module-changed')]
    public function refreshNavigation(): void
    {
        // The open module is stored on the session. Re-rendering reads it.
    }

    public function render(NavigationBuilder $navigation): View
    {
        $workspace = Workspace::tryFrom((string) session('workspace'));
        $user = auth()->user();
        $shell = $workspace && $user ? $navigation->shell($user, $workspace) : [
            'moduleLabel' => '',
            'subnav' => [],
        ];

        return view('livewire.workspace-subnav', [
            'moduleLabel' => $shell['moduleLabel'],
            'items' => $shell['subnav'],
        ]);
    }
}
