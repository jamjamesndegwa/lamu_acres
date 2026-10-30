<x-workspace-layout heading="Manage user">
    <div class="mt-4">
        <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-lamu-800 hover:underline">&larr; User directory</a>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <section class="rounded-2xl border border-sand-200 bg-white p-6 shadow-sm">
            <h2 class="font-serif text-xl text-lamu-900">Account</h2>
            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex flex-wrap gap-x-2">
                    <dt class="font-semibold text-stone-500">Name</dt>
                    <dd class="text-lamu-900">{{ $account->name }}</dd>
                </div>
                <div class="flex flex-wrap gap-x-2">
                    <dt class="font-semibold text-stone-500">Email</dt>
                    <dd class="text-lamu-900">{{ $account->email }}</dd>
                </div>
                @if ($account->phone)
                    <div class="flex flex-wrap gap-x-2">
                        <dt class="font-semibold text-stone-500">Phone</dt>
                        <dd class="text-lamu-900">{{ $account->phone }}</dd>
                    </div>
                @endif
                <div class="flex flex-wrap gap-x-2">
                    <dt class="font-semibold text-stone-500">Login</dt>
                    <dd>
                        @if ($account->isSuspended())
                            <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-900">Suspended</span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-900">Active</span>
                        @endif
                    </dd>
                </div>
                @if ($account->isSuspended() && $account->suspension_reason)
                    <div>
                        <dt class="font-semibold text-stone-500">Suspension reason</dt>
                        <dd class="mt-1 text-stone-700">{{ $account->suspension_reason }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="font-semibold text-stone-500">Roles</dt>
                    <dd class="mt-1 text-stone-700">
                        {{ $account->roles->pluck('label')->filter()->implode(', ') ?: 'No roles assigned' }}
                    </dd>
                </div>
            </dl>

            @if (auth()->id() !== $account->id)
                <div class="mt-6 flex flex-wrap gap-2">
                    <a
                        href="{{ route('admin.users.roles.edit', $account) }}"
                        data-turbo-frame="modal"
                        class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white"
                    >
                        Edit roles
                    </a>
                    <a
                        href="{{ route('admin.users.suspension.edit', $account) }}"
                        data-turbo-frame="modal"
                        class="rounded-full border border-sand-200 px-4 py-2 text-sm font-medium text-lamu-900"
                    >
                        {{ $account->isSuspended() ? 'Restore login' : 'Suspend login' }}
                    </a>
                </div>
            @else
                <p class="mt-6 text-sm text-stone-500">You cannot change roles or access for your own signed-in account here.</p>
            @endif
        </section>

        @if (auth()->id() !== $account->id)
            <section class="rounded-2xl border border-sand-200 bg-white p-6 shadow-sm">
                <h2 class="font-serif text-xl text-lamu-900">Reset password</h2>
                <p class="mt-2 text-sm text-stone-600">
                    Set a new password immediately. Existing sessions for this user are cleared and they receive a security notice by email when delivery is enabled.
                </p>
                <form method="POST" action="{{ route('admin.users.password.update', $account) }}" data-turbo="false" data-confirm="Reset this user&apos;s password?" class="mt-5 space-y-4">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="password" class="block text-sm font-medium">New password</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-2 h-11 w-full rounded-lg border border-sand-200 px-3">
                        @error('password')
                            <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-2 h-11 w-full rounded-lg border border-sand-200 px-3">
                    </div>
                    <button type="submit" class="rounded-full bg-lamu-900 px-5 py-2.5 text-sm font-medium text-white">Reset password</button>
                </form>
            </section>
        @endif
    </div>
</x-workspace-layout>
