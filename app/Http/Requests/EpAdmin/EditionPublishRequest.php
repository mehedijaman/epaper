<?php

namespace App\Http\Requests\EpAdmin;

use Illuminate\Foundation\Http\FormRequest;

class EditionPublishRequest extends FormRequest
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
        ];
    }
}
