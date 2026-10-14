<?php

namespace App\Http\Requests\Staff;

use App\Enums\SourcingRequestStatus;
use App\Models\SourcingRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSourcingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sourcingRequest = $this->route('sourcingRequest');

        return $sourcingRequest instanceof SourcingRequest
            && ($this->user()?->can('updateStatus', $sourcingRequest) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SourcingRequestStatus::class)],
        ];
    }
}
