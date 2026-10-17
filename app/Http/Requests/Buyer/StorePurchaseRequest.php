<?php

namespace App\Http\Requests\Buyer;

use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $property = $this->route('property');

        return $property instanceof Property
            && $property->isPubliclyListed()
            && ! $property->isLease()
            && ($this->user()?->can('enquire', $property) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'requested_price' => ['required', 'integer', 'min:1'],
            'requested_acreage' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'message' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'prohibited'],
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

            if (! $property instanceof Property || ! $this->filled('requested_acreage')) {
                return;
            }

            $requestedAcreage = (float) $this->input('requested_acreage');
            $maximumAcreage = $property->offeredAcreage();
            $minimumAcreage = (float) ($property->minimum_acreage ?? 0);

            if ($requestedAcreage > $maximumAcreage + 0.0001) {
                $validator->errors()->add('requested_acreage', 'The requested acreage exceeds the area offered for sale.');
            } elseif ($minimumAcreage > 0 && $requestedAcreage + 0.0001 < $minimumAcreage) {
                $validator->errors()->add('requested_acreage', 'The requested acreage is below the seller’s minimum portion.');
            }
        }];
    }
}
