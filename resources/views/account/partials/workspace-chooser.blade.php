@if ($workspaces === [])
    <p class="text-stone-700">No workspace has been assigned to this account.</p>
@else
    <div class="space-y-3">
        @foreach ($workspaces as $workspace)
            <form method="POST" action="{{ route('workspaces.switch', $workspace->value) }}" data-turbo="false">
                @csrf
                <button type="submit" class="flex w-full items-center justify-between rounded-2xl border border-sand-200 bg-white px-5 py-4 text-left hover:border-lamu-700">
                    <span>
                        <span class="block font-medium text-lamu-900">{{ $workspace->label() }}</span>
                        <span class="mt-1 block text-sm text-stone-600">{{ ($current ?? null) === $workspace->value ? 'Current workspace' : 'Open this workspace' }}</span>
                    </span>
                    <span class="text-sm font-medium text-lamu-700">Continue</span>
                </button>
            </form>
        @endforeach
    </div>
@endif
