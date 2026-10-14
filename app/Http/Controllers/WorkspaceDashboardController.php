<?php

namespace App\Http\Controllers;

use App\Enums\Workspace;
use App\Models\Role;
use App\Services\Access\NavigationBuilder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceDashboardController extends Controller
{
    public function __invoke(Request $request, NavigationBuilder $navigation): View
    {
        $workspace = Workspace::from((string) $request->session()->get('workspace'));
        $header = $navigation->header($request->user(), $workspace);

        return view('workspaces.dashboard', [
            'workspace' => $workspace,
            'heading' => $header['heading'],
            'intro' => $header['intro'],
            'panels' => $navigation->panels($request->user(), $workspace),
            'activityColumns' => config("workspaces.{$workspace->value}.activity.columns", []),
            'activityEmpty' => config("workspaces.{$workspace->value}.activity.empty"),
            'accessSummary' => $workspace === Workspace::Admin
                ? Role::query()->withCount('users')->orderBy('label')->get()
                : null,
        ]);
    }
}
