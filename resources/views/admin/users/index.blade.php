<x-workspace-layout heading="User directory">
    <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
        <a
            href="{{ route('admin.users.export') }}"
            data-turbo="false"
            data-confirm="Download the current user directory as CSV?"
            class="rounded-full border border-sand-200 bg-white px-4 py-2 text-sm font-medium text-lamu-900 hover:bg-lamu-100"
        >
            Export CSV
        </a>
    </div>

    <div class="mt-6">
        @include('admin.users._table')
    </div>
</x-workspace-layout>
