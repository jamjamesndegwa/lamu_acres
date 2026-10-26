<form
    method="POST"
    action="{{ route('admin.users.roles.update', $account) }}"
    @if ($breakout ?? false) data-turbo-frame="_top" @endif
    data-confirm="Save role changes for {{ $account->name }}?"
    class="space-y-4"
>
    @csrf
    @method('PUT')

    <div class="grid gap-2 sm:grid-cols-2">
        @foreach ($roles as $role)
            <label class="flex items-center gap-2 rounded-lg border border-sand-200 px-3 py-2 text-sm">
                <input
                    type="checkbox"
                    name="roles[]"
                    value="{{ $role->name }}"
                    @checked(collect(old('roles', $account->roles->pluck('name')))->contains($role->name))
                >
                <span>
                    <span class="block font-medium text-lamu-900">{{ $role->label }}</span>
                    @if ($role->description)
                        <span class="mt-0.5 block text-xs text-stone-500">{{ $role->description }}</span>
                    @endif
                </span>
            </label>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-3">
        <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save roles</button>
        @if ($breakout ?? false)
            <button type="button" data-close-modal class="rounded-full border border-sand-200 px-5 py-3 text-sm font-medium text-stone-700">Cancel</button>
        @else
            <a href="{{ route($returnRoute ?? 'admin.users.index') }}" class="rounded-full border border-sand-200 px-5 py-3 text-sm font-medium text-stone-700">Cancel</a>
        @endif
    </div>
</form>
