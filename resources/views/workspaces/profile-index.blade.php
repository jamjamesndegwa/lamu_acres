<x-workspace-layout heading="Profile">
    <section class="mt-6 max-w-3xl rounded-2xl border border-sand-200 bg-white p-5 sm:p-7" aria-labelledby="account-profile-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="account-profile-heading" class="text-lg font-semibold text-lamu-900">Profile</h2>
                <p class="mt-1 text-sm text-stone-600">Manage your photo, contact details, and account security.</p>
            </div>
            <a href="{{ route('buyer.profile.edit') }}" data-turbo-frame="modal" class="inline-flex items-center rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white hover:bg-lamu-800">Edit profile</a>
        </div>

        <div class="mt-5 border-t border-sand-200 pt-5">
            @include('workspaces.partials.avatar-form', ['user' => $user, 'prefix' => 'profile-index-', 'breakout' => true])
        </div>

        <dl class="mt-6 divide-y divide-sand-200 border-t border-sand-200">
            <div class="grid gap-1 py-4 sm:grid-cols-[10rem_1fr] sm:gap-4">
                <dt class="text-sm text-stone-500">Name</dt>
                <dd class="font-medium text-lamu-900">{{ $user->name }}</dd>
            </div>
            <div class="grid gap-1 py-4 sm:grid-cols-[10rem_1fr] sm:gap-4">
                <dt class="text-sm text-stone-500">Email</dt>
                <dd class="font-medium text-lamu-900">{{ $user->email }}</dd>
            </div>
            <div class="grid gap-1 py-4 sm:grid-cols-[10rem_1fr] sm:gap-4">
                <dt class="text-sm text-stone-500">Email verification</dt>
                <dd class="font-medium text-lamu-900">{{ $user->email_verified_at ? 'Verified' : 'Not verified' }}</dd>
            </div>
            <div class="grid gap-1 py-4 sm:grid-cols-[10rem_1fr] sm:gap-4">
                <dt class="text-sm text-stone-500">Phone</dt>
                <dd class="font-medium text-lamu-900">{{ $user->phone ?: 'Not added' }}</dd>
            </div>
            <div class="grid gap-1 py-4 sm:grid-cols-[10rem_1fr] sm:gap-4">
                <dt class="text-sm text-stone-500">Phone verification</dt>
                <dd class="font-medium text-lamu-900">{{ $user->phone_verified_at ? 'Verified' : 'Not verified' }}</dd>
            </div>
        </dl>
    </section>
</x-workspace-layout>
