@props([
    'paginator',
    'frame' => null,
])

<div {{ $attributes }}>
    {{ $paginator->links(data: array_filter(['frame' => $frame])) }}
</div>
