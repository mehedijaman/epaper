<?php

namespace App\Http\Requests\EpAdmin;

use Illuminate\Foundation\Http\FormRequest;

class PageUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('editions.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
            'page_no_strategy' => ['nullable', 'string', 'in:auto,filename,next_available'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:30720'],
        ];
    }
}
