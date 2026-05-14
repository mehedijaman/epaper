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
        $isImage = $this->input('type') === 'image';
        $hasImageFile = $this->hasFile('image_file');
        $isUrlSource = $this->input('image_source') === 'url';

        return [
            'type' => ['required', 'in:image,embed'],
            'image_source' => ['required_if:type,image', 'nullable', 'in:url,upload'],
            'image_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
            'remove_image_file' => ['sometimes', 'boolean'],
            'image_url' => [
                'nullable',
                'string',
                'max:800',
                Rule::requiredIf(fn (): bool => $isImage && $isUrlSource && ! $hasImageFile && ! $this->boolean('remove_image_file')),
            ],
            'click_url' => ['nullable', 'url', 'max:800'],
            'embed_code' => [
                'nullable',
                'string',
                Rule::requiredIf(fn (): bool => $this->input('type') === 'embed'),
            ],
            'slot_title' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
