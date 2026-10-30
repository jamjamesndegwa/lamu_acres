<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommissionCalculationBasis;
use App\Enums\CommissionFunding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaseCommissionPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('admin.commercial') === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'default_calculation_basis' => ['required', Rule::enum(CommissionCalculationBasis::class)],
            'default_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_fixed_amount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'default_monthly_rent_multiplier' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'default_funding_party' => ['required', Rule::enum(CommissionFunding::class)],
            'default_payment_due_days' => ['required', 'integer', 'min:1', 'max:365'],
            'default_protection_months' => ['required', 'integer', 'min:1', 'max:60'],
            'effective_from' => ['nullable', 'date'],
            'terms' => ['required', 'string', 'max:5000'],
            'apply_defaults' => ['nullable', 'boolean'],
        ];
    }
}
