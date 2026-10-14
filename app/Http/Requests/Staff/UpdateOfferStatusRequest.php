<?php

namespace App\Http\Requests\Staff;

use App\Enums\OfferStatus;
use App\Models\Offer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOfferStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $offer = $this->route('offer');

        return $offer instanceof Offer && ($this->user()?->can('updateStatus', $offer) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OfferStatus::class), Rule::notIn([OfferStatus::Withdrawn->value])],
        ];
    }
}
