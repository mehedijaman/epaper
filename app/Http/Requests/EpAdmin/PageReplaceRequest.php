<?php

namespace App\Http\Requests\EpAdmin;

use Illuminate\Foundation\Http\FormRequest;

class PageReplaceRequest extends FormRequest
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
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:30720'],
        ];
    }
}
