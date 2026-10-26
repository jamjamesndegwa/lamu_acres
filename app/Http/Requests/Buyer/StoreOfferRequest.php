<?php

namespace App\Http\Requests\Buyer;

use App\Models\PropertyMatch;
use App\Services\Sales\SaleModeTerms;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $propertyMatch = $this->route('propertyMatch');

        return $propertyMatch instanceof PropertyMatch
            && ($this->user()?->can('offer', $propertyMatch) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'requested_acreage' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $propertyMatch = $this->route('propertyMatch');

            if (! $propertyMatch instanceof PropertyMatch) {
                return;
            }

            $propertyMatch->loadMissing('property');

            try {
                app(SaleModeTerms::class)->validatePurchaseAcreage(
                    $propertyMatch->property,
                    $this->filled('requested_acreage') ? (float) $this->input('requested_acreage') : null,
                );
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        }];
    }
}
