<x-workspace-layout heading="Settings">
    <p class="mt-3 max-w-3xl text-sm text-stone-600">
        Permissions, roles, commercial controls, and system operations for the administration workspace. Open a section below to review or manage it.
    </p>

    <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($cards as $card)
            <x-workspace.card
                :title="$card['title']"
                :description="$card['description']"
                :href="$card['href']"
                :frame="$card['frame'] ?? null"
                :icon="$card['icon']"
            />
        @endforeach
    </div>

    @if ($cards === [])
        <p class="mt-8 rounded-xl border border-sand-200 bg-sand-50 px-4 py-3 text-sm text-stone-700">
            No settings sections are available for your account. Ask a super administrator to grant user or system settings access.
        </p>
    @endif
</x-workspace-layout>
