<?php

namespace App\Http\Middleware;

use App\Enums\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspace
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $workspace): Response
    {
        $target = Workspace::tryFrom($workspace);
        $user = $request->user();

        if ($target === null || $user === null || ! $user->canAccessWorkspace($target)) {
            abort(403);
        }

        $request->session()->put('workspace', $target->value);
        $request->session()->put('workspace_route', $request->route()?->getName());
        $request->session()->forget('workspace_module_pin');

        return $next($request);
    }
}
