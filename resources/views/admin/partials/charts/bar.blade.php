@php
    $rows = $chart['rows'];
    $peak = max(1, max(array_column($rows, 'count')));
    $unit = $chart['unit'] ?? '';
    $width = 640;
    $height = 280;
    $padL = 44;
    $padR = 12;
    $padT = 18;
    $padB = 86;
    $plotW = $width - $padL - $padR;
    $plotH = $height - $padT - $padB;
    $count = max(count($rows), 1);
    $slot = $plotW / $count;
    $barW = max($slot - 14, 8);
@endphp
<svg viewBox="0 0 {{ $width }} {{ $height }}" class="mt-4 h-64 w-full" role="img" aria-label="{{ $chart['heading'] }}">
    @for ($tick = 0; $tick <= 4; $tick++)
        @php
            $value = (int) round($peak * $tick / 4);
            $y = $padT + $plotH - ($plotH * $tick / 4);
        @endphp
        <line x1="{{ $padL }}" y1="{{ $y }}" x2="{{ $width - $padR }}" y2="{{ $y }}" stroke="#e7e0d4" stroke-width="1" />
        <text x="{{ $padL - 8 }}" y="{{ $y + 4 }}" text-anchor="end" font-size="11" fill="#78716c">{{ $unit !== '' ? number_format($value) : $value }}</text>
    @endfor
    <line x1="{{ $padL }}" y1="{{ $padT }}" x2="{{ $padL }}" y2="{{ $padT + $plotH }}" stroke="#d6d3d1" stroke-width="1" />
    @foreach ($rows as $index => $row)
        @php
            $heightPx = ($row['count'] / $peak) * $plotH;
            $x = $padL + ($index * $slot) + (($slot - $barW) / 2);
            $y = $padT + $plotH - $heightPx;
            $labelX = $x + ($barW / 2);
            $labelY = $padT + $plotH + 16;
        @endphp
        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $barW }}" height="{{ $heightPx }}" rx="4" fill="#245744" />
        <text x="{{ $labelX }}" y="{{ max($y - 6, 12) }}" text-anchor="middle" font-size="11" fill="#143028">{{ $unit !== '' ? number_format($row['count']) : number_format($row['count']) }}</text>
        <text x="{{ $labelX }}" y="{{ $labelY }}" text-anchor="end" font-size="11" fill="#44403c" transform="rotate(-35 {{ $labelX }} {{ $labelY }})">{{ $row['label'] }}</text>
    @endforeach
</svg>
