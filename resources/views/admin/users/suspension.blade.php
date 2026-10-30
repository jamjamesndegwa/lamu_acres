<turbo-frame id="modal">
    <x-modal title="Account access" :close="route('admin.users.show', $account)">
        <p class="text-sm text-stone-600">{{ $account->name }} · {{ $account->email }}</p>
        <form method="POST" action="{{ route('admin.users.suspension.update', $account) }}" data-turbo-frame="_top" data-confirm="Update access for this account?" class="mt-5 space-y-4">
            @csrf
            @method('PUT')
            <label class="block text-sm font-medium">
                <input type="radio" name="action" value="suspend" @checked(old('action', $account->suspended_at ? 'suspend' : 'suspend') === 'suspend')>
                Suspend account
            </label>
            <label class="block text-sm font-medium">
                <input type="radio" name="action" value="restore" @checked(old('action') === 'restore')>
                Restore account
            </label>
            <div>
                <label for="suspension_reason" class="block text-sm font-medium">Reason</label>
                <textarea id="suspension_reason" name="suspension_reason" rows="3" maxlength="500" class="mt-1 w-full rounded-lg border border-sand-200 px-3 py-2">{{ old('suspension_reason', $account->suspension_reason) }}</textarea>
                @error('suspension_reason')
                    <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Save access</button>
        </form>
    </x-modal>
</turbo-frame>
