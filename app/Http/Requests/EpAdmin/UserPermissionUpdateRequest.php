<?php

namespace App\Http\Requests\EpAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class UserPermissionUpdateRequest extends FormRequest
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
        /** @var Permission|null $permission */
        $permission = $this->route('permission');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('permissions', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'web'))
                    ->ignore($permission?->id),
            ],
        ];
    }
}
