<?php

namespace App\Http\Requests\EpAdmin;

use Illuminate\Foundation\Http\FormRequest;

class HotspotBulkDestroyRequest extends FormRequest
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
            'page_id' => ['required', 'integer', 'exists:pages,id'],
            'hotspot_ids' => ['required', 'array', 'min:1', 'max:200'],
            'hotspot_ids.*' => ['required', 'integer', 'distinct', 'exists:page_hotspots,id'],
        ];
    }
}
