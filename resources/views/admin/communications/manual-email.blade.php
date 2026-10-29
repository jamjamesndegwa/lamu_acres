<x-workspace-layout heading="Send manual email">
    @if (session('status'))
        <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            {{ session('status') }}
        </div>
    @endif

    @if (! ($mailStatus['smtp_ready'] ?? false))
        <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            SMTP is not fully configured. Set MAIL_* in the environment and run communications:preflight before sending.
        </div>
    @endif

    <form method="GET" action="{{ route('admin.communications.manual-email.create') }}" class="mt-6 flex flex-wrap gap-2 rounded-2xl border border-sand-200 bg-white p-4">
        <label class="flex-1 text-sm">
            <span class="font-medium text-lamu-900">Find user</span>
            <input type="search" name="q" value="{{ $search }}" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2" placeholder="Name or email">
        </label>
        <div class="flex items-end">
            <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Search</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.communications.manual-email.store') }}" data-turbo-frame="_top" data-confirm="Send this email now?" class="mt-6 space-y-4 rounded-2xl border border-sand-200 bg-white p-6">
        @csrf

        <label class="block text-sm">
            <span class="font-medium text-lamu-900">Recipient account</span>
            <select name="user_id" required class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
                <option value="">Choose a user</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('user_id', $selectedUser?->id) == $user->id)>
                        {{ $user->name }} ({{ $user->email ?: 'no email on file' }})
                    </option>
                @endforeach
            </select>
            @error('user_id')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </label>

        <label class="block text-sm">
            <span class="font-medium text-lamu-900">Send to email (optional)</span>
            <input type="email" name="destination_email" value="{{ old('destination_email') }}" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2" placeholder="Leave blank to use the user’s account email">
            @error('destination_email')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </label>

        <label class="block text-sm">
            <span class="font-medium text-lamu-900">Subject</span>
            <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="160" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">
            @error('subject')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </label>

        <label class="block text-sm">
            <span class="font-medium text-lamu-900">Message</span>
            <textarea name="body" rows="8" required maxlength="5000" class="mt-1 w-full rounded-xl border border-sand-200 px-3 py-2">{{ old('body') }}</textarea>
            @error('body')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </label>

        <div class="flex flex-wrap gap-2">
            <button type="submit" class="rounded-full bg-lamu-900 px-4 py-2 text-sm font-medium text-white">Send email</button>
            <a href="{{ route('admin.communications.external.index') }}" class="rounded-full border border-sand-200 px-4 py-2 text-sm font-medium text-lamu-900">Back to register</a>
        </div>
    </form>
</x-workspace-layout>
