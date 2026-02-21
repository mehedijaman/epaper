<?php

namespace App\Http\Requests\EpAdmin;

use Illuminate\Foundation\Http\FormRequest;

class PageReorderRequest extends FormRequest
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
            'ordered_page_ids' => ['required', 'array', 'min:1'],
            'ordered_page_ids.*' => ['required', 'integer', 'distinct', 'exists:pages,id'],
        ];
    }
}
