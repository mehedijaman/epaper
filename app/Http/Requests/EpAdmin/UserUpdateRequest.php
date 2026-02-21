<?php

namespace App\Http\Requests\EpAdmin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('users.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $password = $this->input('password');
        $passwordConfirmation = $this->input('password_confirmation');

        $this->merge([
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
            'email' => is_string($this->input('email')) ? trim((string) $this->input('email')) : $this->input('email'),
            'password' => is_string($password) && trim($password) === '' ? null : $password,
            'password_confirmation' => is_string($passwordConfirmation) && trim($passwordConfirmation) === '' ? null : $passwordConfirmation,
        ]);
    }
}
