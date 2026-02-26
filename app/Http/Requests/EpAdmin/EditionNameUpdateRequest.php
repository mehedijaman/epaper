<?php

namespace App\Http\Requests\EpAdmin;

use App\Models\Edition;
use Illuminate\Foundation\Http\FormRequest;

class EditionNameUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $edition = $this->route('edition');

        if (! $edition instanceof Edition) {
            return false;
        }

        return (bool) $this->user()?->can('update', $edition);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:150'],
        ];
    }
}
