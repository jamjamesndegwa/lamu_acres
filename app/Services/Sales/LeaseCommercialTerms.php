<?php

namespace App\Services\Sales;

use App\Enums\CommissionCalculationBasis;
use App\Enums\DealKind;
use App\Enums\RentFrequency;
use App\Models\SalesTransaction;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class LeaseCommercialTerms
{
    public function assertLeaseDeal(SalesTransaction $transaction): void
    {
        if ($transaction->dealKind() !== DealKind::Lease) {
            throw ValidationException::withMessages([
                'deal_kind' => 'This action applies only to a lease deal file.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function applyCommercialTerms(SalesTransaction $transaction, array $data): void
    {
        $this->assertLeaseDeal($transaction);

        if (blank($data['lease_starts_on'] ?? null)) {
            throw ValidationException::withMessages(['lease_starts_on' => 'Record the lease commencement date.']);
        }

        if (blank($data['agreed_rent'] ?? null)) {
            throw ValidationException::withMessages(['agreed_rent' => 'Record the agreed rent.']);
        }

        $frequency = RentFrequency::tryFrom((string) ($data['rent_frequency'] ?? ''));
        if ($frequency === null) {
            throw ValidationException::withMessages(['rent_frequency' => 'Choose how rent is quoted.']);
        }

        $transaction->lease_starts_on = $data['lease_starts_on'];
        $transaction->lease_ends_on = $data['lease_ends_on'] ?? null;
        $transaction->rent_frequency = $frequency;
        $transaction->agreed_rent = (int) $data['agreed_rent'];
        $transaction->lease_premium = filled($data['lease_premium'] ?? null) ? (int) $data['lease_premium'] : null;
        $transaction->acreage = round((float) ($data['acreage'] ?? $transaction->acreage ?? $transaction->property->offeredAcreage()), 4);
    }

    public function firstYearContractedRent(SalesTransaction $transaction): ?int
    {
        if ($transaction->agreed_rent === null || $transaction->rent_frequency === null || $transaction->lease_starts_on === null) {
            return null;
        }

        $frequency = $transaction->rent_frequency instanceof RentFrequency
            ? $transaction->rent_frequency
            : RentFrequency::from((string) $transaction->rent_frequency);

        $start = $transaction->lease_starts_on;
        $end = $transaction->lease_ends_on;
        $monthsInTerm = $this->monthsInFirstContractYear($start, $end);

        return match ($frequency) {
            RentFrequency::Monthly => (int) round((int) $transaction->agreed_rent * min(12, $monthsInTerm)),
            RentFrequency::Annual => $this->prorateAnnualRent((int) $transaction->agreed_rent, $monthsInTerm),
        };
    }

    public function monthlyRentAmount(SalesTransaction $transaction): ?int
    {
        if ($transaction->agreed_rent === null || $transaction->rent_frequency === null) {
            return null;
        }

        $frequency = $transaction->rent_frequency instanceof RentFrequency
            ? $transaction->rent_frequency
            : RentFrequency::from((string) $transaction->rent_frequency);

        return match ($frequency) {
            RentFrequency::Monthly => (int) $transaction->agreed_rent,
            RentFrequency::Annual => (int) round((int) $transaction->agreed_rent / 12),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function calculationInputs(SalesTransaction $transaction): array
    {
        return [
            'agreed_rent' => $transaction->agreed_rent,
            'rent_frequency' => $transaction->rent_frequency instanceof RentFrequency
                ? $transaction->rent_frequency->value
                : $transaction->rent_frequency,
            'lease_premium' => $transaction->lease_premium,
            'first_year_contracted_rent' => $this->firstYearContractedRent($transaction),
            'monthly_rent' => $this->monthlyRentAmount($transaction),
            'lease_starts_on' => $transaction->lease_starts_on?->toDateString(),
            'lease_ends_on' => $transaction->lease_ends_on?->toDateString(),
        ];
    }

    public function calculateAmount(
        CommissionCalculationBasis $basis,
        SalesTransaction $transaction,
        ?float $ratePercent,
        ?int $fixedAmount,
        ?float $monthlyRentMultiplier,
    ): ?int {
        return match ($basis) {
            CommissionCalculationBasis::Fixed => $fixedAmount,
            CommissionCalculationBasis::AgreedSalePrice => $this->percentOf($transaction->agreed_price, $ratePercent),
            CommissionCalculationBasis::FirstYearRentPercent => $this->percentOf($this->firstYearContractedRent($transaction), $ratePercent),
            CommissionCalculationBasis::MonthlyRentMultiple => $this->multipleOfMonthlyRent($transaction, $monthlyRentMultiplier),
            CommissionCalculationBasis::LeasePremiumPercent => $this->percentOf($transaction->lease_premium, $ratePercent),
        };
    }

    private function percentOf(?int $base, ?float $ratePercent): ?int
    {
        if ($base === null || $ratePercent === null) {
            return null;
        }

        return (int) round($base * $ratePercent / 100);
    }

    private function multipleOfMonthlyRent(SalesTransaction $transaction, ?float $multiplier): ?int
    {
        $monthly = $this->monthlyRentAmount($transaction);
        if ($monthly === null || $multiplier === null) {
            return null;
        }

        return (int) round($monthly * $multiplier);
    }

    private function prorateAnnualRent(int $annualRent, int $monthsInFirstYear): int
    {
        if ($monthsInFirstYear >= 12) {
            return $annualRent;
        }

        return (int) round($annualRent * ($monthsInFirstYear / 12));
    }

    private function monthsInFirstContractYear(CarbonInterface $start, ?CarbonInterface $end): int
    {
        $firstYearEnd = $start->copy()->addYear()->subDay();

        if ($end !== null && $end->lt($firstYearEnd)) {
            return max(1, (int) $start->diffInMonths($end));
        }

        return 12;
    }
}
