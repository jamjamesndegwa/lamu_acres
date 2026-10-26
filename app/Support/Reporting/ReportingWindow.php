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

    public static function allRecords(): self
    {
        return new self(
            CarbonImmutable::parse('1970-01-01')->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
            'all',
        );
    }

    public function isAllRecords(): bool
    {
        return $this->range === 'all';
    }

    public static function fromRequest(Request $request): self
    {
        $range = $request->string('range')->toString();

        if ($range === '') {
            $range = '30';
        }

        if (! in_array($range, ['today', '7', '30', 'month', 'last_month', 'quarter', 'year', 'custom'], true)) {
            throw ValidationException::withMessages([
                'range' => 'Choose a supported reporting period.',
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
            'month' => CarbonImmutable::now()->startOfMonth(),
            'last_month' => CarbonImmutable::now()->subMonth()->startOfMonth(),
            'quarter' => CarbonImmutable::now()->startOfQuarter(),
            'year' => CarbonImmutable::now()->startOfYear(),
            default => CarbonImmutable::now()->subDays(30)->startOfDay(),
        };

        if ($range === 'last_month') {
            $to = CarbonImmutable::now()->subMonth()->endOfMonth()->endOfDay();
        }

        return new self($from, $to, $range);
    }

    public function label(): string
    {
        if ($this->isAllRecords()) {
            return 'All records';
        }

        return $this->from->toFormattedDateString().' – '.$this->to->toFormattedDateString();
    }

    /**
     * @return array{range: string, from?: string, to?: string}
     */
    public function query(): array
    {
        if ($this->isAllRecords()) {
            return [];
        }

        $query = ['range' => $this->range];

        if ($this->range === 'custom') {
            $query['from'] = $this->from->toDateString();
            $query['to'] = $this->to->toDateString();
        }

        return $query;
    }
}
