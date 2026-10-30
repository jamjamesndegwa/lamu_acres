<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $account = $this->route('user');

        if (! $actor instanceof User || ! $account instanceof User) {
            return false;
        }

        if ($actor->is($account)) {
            return false;
        }

        return $actor->hasPermission('admin.users');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function redirectTo(): string
    {
        /** @var User $user */
        $user = $this->route('user');

        return route('admin.users.show', $user);
    }
}
