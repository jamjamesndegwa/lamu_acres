<?php

namespace App\Http\Requests\Buyer;

use App\Models\PropertyMatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
