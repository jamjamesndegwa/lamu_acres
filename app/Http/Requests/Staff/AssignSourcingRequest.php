<?php

namespace App\Http\Requests\Staff;

use App\Models\SourcingRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignSourcingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sourcingRequest = $this->route('sourcingRequest');

        return $sourcingRequest instanceof SourcingRequest
            && ($this->user()?->can('assign', $sourcingRequest) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }

    public function assignee(): User
    {
        $assignee = User::query()->findOrFail($this->integer('assigned_to'));
        abort_unless($assignee->hasPermission('workspace.staff'), 422);

        return $assignee;
    }
}
