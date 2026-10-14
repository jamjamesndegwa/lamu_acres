<?php

namespace App\Http\Requests;

use App\Enums\Workspace;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function getRedirectUrl(): string
    {
        $workspace = Workspace::tryFrom((string) $this->session()->get('workspace'));

        return $workspace
            ? route($workspace->value.'.profile')
            : $this->redirector->getUrlGenerator()->previous();
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('phone') === '') {
            $this->merge(['phone' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'phone' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'phone')->ignore($this->user()?->id),
                Rule::unique('users', 'pending_phone')->ignore($this->user()?->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'current_password' => ['required_with:password', 'current_password'],
            'notify_by_email' => ['sometimes', 'boolean'],
        ];
    }
}
