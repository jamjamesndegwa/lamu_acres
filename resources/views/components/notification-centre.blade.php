@props([
    'workspace' => null,
    'count' => 0,
])

@if ($workspace && \Illuminate\Support\Facades\Route::has($workspace->value.'.notifications.index'))
    <a href="{{ route($workspace->value.'.notifications.index') }}" class="rounded-md text-sm font-medium text-lamu-900 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-lamu-700">
        Notifications
        @if ((int) $count > 0)
            ({{ $count }})
        @endif
    </a>
@endif
