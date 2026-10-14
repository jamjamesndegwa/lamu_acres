@php
    $rows = array_values($chart['rows']);
    $count = max(count($rows), 3);
    $peak = max(1, max(array_column($rows, 'count')));
    $unit = $chart['unit'] ?? '';
    $cx = 180;
    $cy = 168;
    $radius = 108;
    $point = function (int $index, float $scale) use ($count, $cx, $cy, $radius): array {
        $angle = (-M_PI / 2) + ((2 * M_PI * $index) / $count);

        return [
            $cx + (cos($angle) * $radius * $scale),
            $cy + (sin($angle) * $radius * $scale),
        ];
    };
    $ring = function (float $scale) use ($count, $point): string {
        $pairs = [];
        for ($index = 0; $index < $count; $index++) {
            [$x, $y] = $point($index, $scale);
            $pairs[] = round($x, 1).','.round($y, 1);
        }

        return implode(' ', $pairs);
    };
    $data = [];
    foreach ($rows as $index => $row) {
        [$x, $y] = $point($index, $row['count'] / $peak);
        $data[] = round($x, 1).','.round($y, 1);
    }
@endphp
<svg viewBox="0 0 360 340" class="mx-auto mt-2 h-72 w-full max-w-md" role="img" aria-label="{{ $chart['heading'] }}">
    @foreach ([0.25, 0.5, 0.75, 1] as $scale)
        <polygon points="{{ $ring($scale) }}" fill="none" stroke="#e7e0d4" stroke-width="1" />
    @endforeach
    @for ($index = 0; $index < $count; $index++)
        @php [$x, $y] = $point($index, 1); @endphp
        <line x1="{{ $cx }}" y1="{{ $cy }}" x2="{{ $x }}" y2="{{ $y }}" stroke="#e7e0d4" stroke-width="1" />
    @endfor
    <polygon points="{{ implode(' ', $data) }}" fill="#245744" fill-opacity="0.35" stroke="#143028" stroke-width="2" />
    @foreach ($rows as $index => $row)
        @php
            [$x, $y] = $point($index, 1.18);
            $anchor = $x < $cx - 8 ? 'end' : ($x > $cx + 8 ? 'start' : 'middle');
        @endphp
        <text x="{{ $x }}" y="{{ $y }}" text-anchor="{{ $anchor }}" font-size="11" fill="#143028">{{ $row['label'] }}</text>
    @endforeach
</svg>
<ul class="mt-2 grid gap-1 text-sm text-stone-600 sm:grid-cols-2">
    @foreach ($rows as $row)
        <li class="flex justify-between gap-3">
            <span>{{ $row['label'] }}</span>
            <span class="font-medium text-lamu-900">{{ $unit !== '' ? $unit.' '.number_format($row['count']) : number_format($row['count']) }}</span>
        </li>
    @endforeach
</ul>
<p class="mt-2 text-xs text-stone-500">The rings are the scale. The filled polygon is the recorded total.</p>
