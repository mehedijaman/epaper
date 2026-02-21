<?php

namespace App\Http\Requests\EpAdmin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('categories.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:150'],
            'position' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('categories', 'position')->ignore($category->id),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
