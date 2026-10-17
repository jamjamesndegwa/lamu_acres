<form
    method="POST"
    action="{{ route('account.avatar.store') }}"
    enctype="multipart/form-data"
    @if ($breakout ?? false) data-turbo-frame="_top" @endif
    class="space-y-2"
>
    @csrf
    <x-upload-manager :id="$prefix.'avatar'" name="avatar" :label="$user->avatar_path ? 'Change photo' : 'Add photo'" accept="image/jpeg,image/png,image/webp" required />
    @error('avatar')
        <p class="text-sm text-red-700">{{ $message }}</p>
    @enderror
    <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Save photo</button>
</form>
@if ($user->avatar_path)
    <form method="POST" action="{{ route('account.avatar.destroy') }}" data-confirm="Remove this profile photo?" @if ($breakout ?? false) data-turbo-frame="_top" @endif>
        @csrf
        @method('DELETE')
        <button type="submit" class="text-sm text-stone-600 hover:underline">Remove photo</button>
    </form>
@endif
