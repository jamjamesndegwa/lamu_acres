<?php

namespace App\Support;

use App\Enums\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApprovalsReturn
{
    public static function back(Request $request, string $tab, string $status): RedirectResponse
    {
        if (self::wants($request)) {
            return redirect()
                ->route('admin.approvals.index', ['tab' => $tab])
                ->with('status', $status);
        }

        return back()->with('status', $status);
    }

    public static function orRedirect(Request $request, string $tab, string $status, string $fallback): RedirectResponse
    {
        if (self::wants($request)) {
            return redirect()
                ->route('admin.approvals.index', ['tab' => $tab])
                ->with('status', $status);
        }

        return redirect($fallback)->with('status', $status);
    }

    private static function wants(Request $request): bool
    {
        $user = $request->user();

        return $request->input('return') === 'approvals'
            && $user?->hasPermission('admin.approvals') === true
            && $user->canAccessWorkspace(Workspace::Admin);
    }
}
