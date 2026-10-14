<?php

namespace App\Http\Requests\Buyer;

use App\Models\PropertyMatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        $propertyMatch = $this->route('propertyMatch');

        return $propertyMatch instanceof PropertyMatch
            && ($this->user()?->can('requestVisit', $propertyMatch) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'requested_for' => ['required', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
