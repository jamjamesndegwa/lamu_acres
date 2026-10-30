<?php

namespace App\Http\Requests\Admin;

use App\Enums\AgencyFeePayer;
use App\Enums\FeeCalculationBasis;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAgencyFeePolicyRequest extends FormRequest
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
            'default_calculation_basis' => ['required', Rule::enum(FeeCalculationBasis::class)],
            'default_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_fixed_amount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'default_monthly_rent_multiplier' => ['nullable', 'numeric', 'min:0', 'max:120'],
            'default_fee_payer' => ['required', Rule::enum(AgencyFeePayer::class)],
            'effective_from' => ['nullable', 'date'],
            'terms' => ['required', 'string', 'max:5000'],
            'apply_defaults' => ['nullable', 'boolean'],
        ];
    }
}
