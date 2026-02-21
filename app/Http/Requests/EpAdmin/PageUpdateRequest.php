<?php

namespace App\Http\Requests\EpAdmin;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageUpdateRequest extends FormRequest
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
        $routePage = $this->route('page');
        $page = $routePage instanceof Page ? $routePage : null;
        $pageId = $page?->id ?? (is_numeric($routePage) ? (int) $routePage : 0);
        $editionId = $page?->edition_id ?? 0;

        return [
            'page_no' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
                Rule::unique('pages', 'page_no')
                    ->where(fn ($query) => $query->where('edition_id', $editionId))
                    ->ignore($pageId),
            ],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
