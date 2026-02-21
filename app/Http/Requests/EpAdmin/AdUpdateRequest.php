<?php

namespace App\Http\Requests\EpAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('ads.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:image,embed'],
            'image_url' => [
                'nullable',
                'string',
                'max:800',
                Rule::requiredIf(fn (): bool => $this->input('type') === 'image'),
            ],
            'click_url' => ['nullable', 'url', 'max:800'],
            'embed_code' => [
                'nullable',
                'string',
                Rule::requiredIf(fn (): bool => $this->input('type') === 'embed'),
            ],
            'is_active' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
