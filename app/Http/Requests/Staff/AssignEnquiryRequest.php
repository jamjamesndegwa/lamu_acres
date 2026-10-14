<?php

namespace App\Http\Requests\Staff;

use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $enquiry = $this->route('enquiry');

        return $enquiry instanceof Enquiry && ($this->user()?->can('assign', $enquiry) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ],
        ];
    }

    public function assignee(): User
    {
        $assignee = User::query()->findOrFail($this->integer('assigned_to'));

        abort_unless($assignee->hasPermission('workspace.staff'), 422);

        return $assignee;
    }
}
