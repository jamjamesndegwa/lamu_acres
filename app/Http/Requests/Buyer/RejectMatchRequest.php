<?php

namespace App\Http\Requests\Buyer;

use App\Models\PropertyMatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $propertyMatch = $this->route('propertyMatch');

        return $propertyMatch instanceof PropertyMatch
            && ($this->user()?->can('reject', $propertyMatch) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'buyer_feedback' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
