<?php

namespace App\Http\Requests\EpAdmin;

use App\Models\Ad;
use Illuminate\Foundation\Http\FormRequest;

class AdStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Ad::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ad_slot_id' => ['required', 'integer', 'exists:ad_slots,id'],
            'type' => ['required', 'in:image,embed'],
            'image_url' => ['nullable', 'required_if:type,image', 'url', 'max:2048'],
            'click_url' => ['nullable', 'url', 'max:2048'],
            'embed_code' => ['nullable', 'required_if:type,embed', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
