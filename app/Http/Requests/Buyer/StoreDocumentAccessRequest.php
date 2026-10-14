<?php

namespace App\Http\Requests\Buyer;

use App\Models\PropertyMatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $propertyMatch = $this->route('propertyMatch');

        return $propertyMatch instanceof PropertyMatch
            && ($this->user()?->can('requestDocuments', $propertyMatch) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
