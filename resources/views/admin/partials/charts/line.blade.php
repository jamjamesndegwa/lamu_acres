@php
    $series = array_values($chart['series']);
    $colors = ['#245744', '#a68548', '#143028'];
    $sample = $series[0]['points'] ?? [];
    $pointCount = max(count($sample), 1);
    $peak = 1;
    foreach ($series as $line) {
        foreach ($line['points'] as $point) {
            $peak = max($peak, (int) $point['count']);
        }
    }
    $width = 640;
    $height = 260;
    $padL = 36;
    $padR = 12;
    $padT = 16;
    $padB = 48;
    $plotW = $width - $padL - $padR;
    $plotH = $height - $padT - $padB;
    $step = $pointCount === 1 ? 0 : $plotW / ($pointCount - 1);
    $xy = function (int $index, int $value) use ($padL, $padT, $plotH, $step, $peak): array {
        return [
            round($padL + ($index * $step), 1),
            round($padT + $plotH - (($value / $peak) * $plotH), 1),
        ];
    };
@endphp
<svg viewBox="0 0 {{ $width }} {{ $height }}" class="mt-4 h-64 w-full" role="img" aria-label="{{ $chart['heading'] }}">
    @for ($tick = 0; $tick <= 4; $tick++)
        @php
            $value = (int) round($peak * $tick / 4);
            $y = $padT + $plotH - ($plotH * $tick / 4);
        @endphp
        <line x1="{{ $padL }}" y1="{{ $y }}" x2="{{ $width - $padR }}" y2="{{ $y }}" stroke="#e7e0d4" stroke-width="1" />
        <text x="{{ $padL - 6 }}" y="{{ $y + 4 }}" text-anchor="end" font-size="11" fill="#78716c">{{ $value }}</text>
    @endfor
    @foreach ($series as $seriesIndex => $line)
        @php
            $pairs = [];
            $area = [];
            foreach ($line['points'] as $index => $point) {
                [$x, $y] = $xy($index, (int) $point['count']);
                $pairs[] = $x.','.$y;
                $area[] = $x.','.$y;
            }
            if ($pairs !== []) {
                [$firstX] = $xy(0, 0);
                [$lastX, $baseY] = $xy(count($line['points']) - 1, 0);
                $areaPath = $firstX.','.$baseY.' '.implode(' ', $area).' '.$lastX.','.$baseY;
            } else {
                $areaPath = '';
            }
            $color = $colors[$seriesIndex % count($colors)];
        @endphp
        @if ($areaPath !== '')
            <polygon points="{{ $areaPath }}" fill="{{ $color }}" fill-opacity="0.16" stroke="none" />
            <polyline points="{{ implode(' ', $pairs) }}" fill="none" stroke="{{ $color }}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
        @endif
    @endforeach
    @foreach ($sample as $index => $point)
        @php
            $show = $pointCount <= 8 || $index % (int) ceil($pointCount / 6) === 0 || $index === $pointCount - 1;
            [$x] = $xy($index, 0);
        @endphp
        @if ($show)
            <text x="{{ $x }}" y="{{ $height - 16 }}" text-anchor="middle" font-size="11" fill="#44403c">{{ $point['label'] }}</text>
        @endif
    @endforeach
</svg>
<ul class="mt-3 flex flex-wrap gap-4 text-sm text-stone-600">
    @foreach ($series as $seriesIndex => $line)
        <li class="inline-flex items-center gap-2">
            <span class="inline-block h-2 w-6 rounded-full" style="background: {{ $colors[$seriesIndex % count($colors)] }}"></span>
            {{ $line['label'] }}
        </li>
    @endforeach
</ul>
