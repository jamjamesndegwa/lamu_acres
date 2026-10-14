<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRolesRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\RoleAssigner;
use App\Support\CsvExport;
use App\Support\Turbo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserAccessController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()->with('roles')->orderBy('name')->paginate(20);

        if (Turbo::frame($request, 'users')) {
            return view('admin.users._table', compact('users'));
        }

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user): View
    {
        abort_if(request()->user()->is($user), 403);

        return view('admin.users.roles', [
            'account' => $user->load('roles'),
            'roles' => Role::query()->orderBy('label')->get(),
        ]);
    }

    public function update(AssignRolesRequest $request, User $user, RoleAssigner $roles): RedirectResponse
    {
        if ($request->user()->is($user)) {
            abort(403);
        }

        $roles->sync($user, $request->validated('roles') ?? []);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'Roles updated.');
    }

    public function editSuspension(User $user): View
    {
        abort_if(request()->user()->is($user), 403);

        return view('admin.users.suspension', [
            'account' => $user,
        ]);
    }

    public function updateSuspension(Request $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user), 403);

        $data = $request->validate([
            'action' => ['required', 'in:suspend,restore'],
            'suspension_reason' => ['required_if:action,suspend', 'nullable', 'string', 'max:500'],
        ]);

        if ($data['action'] === 'suspend') {
            $user->forceFill([
                'suspended_at' => now(),
                'suspension_reason' => $data['suspension_reason'],
            ])->save();
            $status = 'Account suspended.';
        } else {
            $user->forceFill([
                'suspended_at' => null,
                'suspension_reason' => null,
            ])->save();
            $status = 'Account restored.';
        }

        return redirect()->route('admin.users.index')->with('status', $status);
    }

    public function export(): StreamedResponse
    {
        $users = User::query()->with('roles')->orderBy('name')->get();

        return CsvExport::download(
            'lamu-acres-users.csv',
            ['Name', 'Email', 'Roles'],
            $users->map(fn (User $user): array => [
                $user->name,
                $user->email,
                $user->roles->pluck('label')->join(', '),
            ]),
        );
    }
}
