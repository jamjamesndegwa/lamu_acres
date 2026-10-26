<?php

namespace App\Http\Requests\Buyer;

use App\Models\Property;
use App\Services\Sales\SaleModeTerms;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StorePublicOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            && ($this->user()?->can('offer', $property) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
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

            $property = $this->route('property');

            if (! $property instanceof Property) {
                return;
            }

            try {
                app(SaleModeTerms::class)->validatePurchaseAcreage(
                    $property,
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
