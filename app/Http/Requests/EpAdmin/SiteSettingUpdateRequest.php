<?php

namespace App\Http\Requests\EpAdmin;

use Illuminate\Foundation\Http\FormRequest;

class SiteSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('settings.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
            'remove_logo' => ['sometimes', 'boolean'],
            'footer_editor_info' => ['nullable', 'string'],
            'footer_contact_info' => ['nullable', 'string'],
            'footer_copyright' => ['nullable', 'string'],
        ];
    }
}
