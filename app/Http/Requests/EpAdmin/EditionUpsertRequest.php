<?php

namespace App\Http\Requests\EpAdmin;

use App\Models\Edition;
use Illuminate\Foundation\Http\FormRequest;

class EditionUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Edition::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'edition_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
