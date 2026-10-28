@props([
    'watch' => null,
    'localityId' => null,
    'projectId' => null,
    'label' => 'Watch',
    'watchingLabel' => 'Watching',
])

@if (auth()->user()?->hasActiveInvestorPass())
    @if ($watch)
        <form method="POST" action="{{ route('investor.watchlists.destroy', $watch) }}" class="inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="inline-flex min-h-10 items-center rounded-full border border-lamu-800 bg-lamu-100 px-4 py-2 text-sm font-medium text-lamu-900 hover:bg-lamu-200">
                {{ $watchingLabel }} · Unfollow
            </button>
        </form>
    @else
        <form method="POST" action="{{ route('investor.watchlists.store') }}" class="inline">
            @csrf
            @if ($localityId)
                <input type="hidden" name="locality_id" value="{{ $localityId }}">
            @endif
            @if ($projectId)
                <input type="hidden" name="development_project_id" value="{{ $projectId }}">
            @endif
            <button type="submit" class="inline-flex min-h-10 items-center rounded-full border border-lamu-800 px-4 py-2 text-sm font-medium text-lamu-900 hover:bg-lamu-100">
                {{ $label }}
            </button>
        </form>
    @endif
@endif
