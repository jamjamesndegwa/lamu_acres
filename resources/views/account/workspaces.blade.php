<x-account-layout title="Workspaces — Lamu Acres">
    <h1 class="text-2xl font-semibold text-lamu-900">Choose a workspace</h1>

    @if ($workspaces === [])
        <p class="mt-4 text-stone-700">No workspace has been assigned to this account.</p>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($workspaces as $workspace)
                <form method="POST" action="{{ route('workspaces.switch', $workspace->value) }}" data-turbo="false" data-confirm="Open the {{ $workspace->label() }} workspace?">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-between rounded-2xl border border-sand-200 bg-white px-5 py-4 text-left hover:border-lamu-700">
                        <span>
                            <span class="block font-medium text-lamu-900">{{ $workspace->label() }}</span>
                            <span class="mt-1 block text-sm text-stone-600">Open this workspace</span>
                        </span>
                        <span class="text-sm font-medium text-lamu-700">Continue</span>
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</x-account-layout>
