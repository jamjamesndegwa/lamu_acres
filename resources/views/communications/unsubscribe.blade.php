<x-auth-layout title="Unsubscribe — Lamu Acres">
        <h1 class="text-2xl font-semibold text-lamu-900">Marketing preferences</h1>
        <p class="mt-3 text-stone-700">Account: {{ $user->email }}</p>
        @if (session('status'))
            <p class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">{{ session('status') }}</p>
        @else
            <p class="mt-4 text-stone-700">Stop marketing email and SMS from Lamu Acres. Transactional account messages may still be sent when required.</p>
            <form method="POST" action="{{ route('communications.unsubscribe.update', $token) }}" class="mt-6">
                @csrf
                <button type="submit" class="rounded-full bg-lamu-900 px-5 py-3 text-sm font-medium text-white">Unsubscribe from marketing</button>
            </form>
        @endif
</x-auth-layout>
