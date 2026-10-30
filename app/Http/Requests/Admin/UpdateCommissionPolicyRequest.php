<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommissionCalculationBasis;
use App\Enums\CommissionFunding;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCommissionPolicyRequest extends FormRequest
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
            'default_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_fixed_amount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'default_funding_party' => ['required', Rule::enum(CommissionFunding::class)],
            'default_payment_due_days' => ['required', 'integer', 'min:1', 'max:365'],
            'default_protection_months' => ['required', 'integer', 'min:1', 'max:60'],
            'default_calculation_basis' => ['nullable', Rule::enum(CommissionCalculationBasis::class)],
            'effective_from' => ['nullable', 'date'],
            'terms' => ['required', 'string', 'max:5000'],
            'apply_defaults' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'default_payment_due_days.required' => 'Enter the default payment due period in days.',
            'default_protection_months.required' => 'Enter the default protection period in months.',
            'terms.required' => 'Describe the commission policy staff should follow.',
        ];
    }
}
