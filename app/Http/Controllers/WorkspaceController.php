<?php

namespace App\Http\Controllers;

use App\Enums\Workspace;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.workspaces', [
            'workspaces' => $request->user()->workspaces(),
            'current' => $request->session()->get('workspace'),
            'modal' => Turbo::wantsModal($request),
        ]);
    }

    public function switch(Request $request, string $workspace): RedirectResponse
    {
        $target = Workspace::tryFrom($workspace);

        if ($target === null || ! $request->user()->canAccessWorkspace($target)) {
            abort(403);
        }

        $request->session()->put('workspace', $target->value);

        return redirect()->route($target->homeRoute());
    }
}
