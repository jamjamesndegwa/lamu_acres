<?php

namespace App\Support\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ReportingWindow
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $range,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $range = $request->string('range')->toString();

        if ($range === '') {
            $range = '30';
        }

        if (! in_array($range, ['today', '7', '30', 'custom'], true)) {
            throw ValidationException::withMessages([
                'range' => 'Choose today, 7 days, 30 days, or a custom range.',
            ]);
        }

        if ($range === 'custom') {
            $dates = $request->validate([
                'from' => ['required', 'date'],
                'to' => ['required', 'date', 'after_or_equal:from'],
            ]);

            return new self(
                CarbonImmutable::parse($dates['from'])->startOfDay(),
                CarbonImmutable::parse($dates['to'])->endOfDay(),
                'custom',
            );
        }

        $to = CarbonImmutable::now()->endOfDay();
        $from = match ($range) {
            'today' => CarbonImmutable::now()->startOfDay(),
            '7' => CarbonImmutable::now()->subDays(7)->startOfDay(),
            default => CarbonImmutable::now()->subDays(30)->startOfDay(),
        };

        return new self($from, $to, $range);
    }

    public function label(): string
    {
        return $this->from->toFormattedDateString().' – '.$this->to->toFormattedDateString();
    }

    /**
     * @return array{range: string, from?: string, to?: string}
     */
    public function query(): array
    {
        $query = ['range' => $this->range];

        if ($this->range === 'custom') {
            $query['from'] = $this->from->toDateString();
            $query['to'] = $this->to->toDateString();
        }

        return $query;
    }
}
