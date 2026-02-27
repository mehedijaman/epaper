<?php

namespace App\Http\Requests\EpAdmin;

use App\Models\Page;
use App\Models\PageHotspot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class HotspotStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', PageHotspot::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page_id' => ['required', 'integer', 'exists:pages,id'],
            'relation_kind' => ['required', 'in:next,previous'],
            'target_page_no' => ['nullable', 'integer', 'min:1'],
            'target_hotspot_id' => ['nullable', 'integer', 'exists:page_hotspots,id'],
            'x' => ['required', 'numeric', 'between:0,1'],
            'y' => ['required', 'numeric', 'between:0,1'],
            'w' => ['required', 'numeric', 'gt:0', 'between:0,1'],
            'h' => ['required', 'numeric', 'gt:0', 'between:0,1'],
            'label' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $pageId = (int) $this->input('page_id');
            $editionId = Page::query()->whereKey($pageId)->value('edition_id');

            if ($editionId === null) {
                return;
            }

            $maxPageNo = (int) Page::query()
                ->where('edition_id', $editionId)
                ->max('page_no');

            $targetPageNoInput = $this->input('target_page_no');
            $targetPageNo = is_numeric($targetPageNoInput)
                ? (int) $targetPageNoInput
                : null;

            if (
                $targetPageNo !== null &&
                ($targetPageNo < 1 || $targetPageNo > $maxPageNo)
            ) {
                $validator->errors()->add(
                    'target_page_no',
                    sprintf('Target page must be between 1 and %d for this edition.', max($maxPageNo, 1)),
                );
            }

            $x = (float) $this->input('x');
            $y = (float) $this->input('y');
            $w = (float) $this->input('w');
            $h = (float) $this->input('h');

            if (($x + $w) > 1.0) {
                $validator->errors()->add('w', 'x + w must be less than or equal to 1.');
            }

            if (($y + $h) > 1.0) {
                $validator->errors()->add('h', 'y + h must be less than or equal to 1.');
            }

            $targetHotspotId = $this->input('target_hotspot_id');

            if ($targetHotspotId === null || $targetHotspotId === '') {
                return;
            }

            $targetHotspot = PageHotspot::query()
                ->with(['page:id,edition_id,page_no'])
                ->find((int) $targetHotspotId);

            if ($targetHotspot === null || $targetHotspot->page === null) {
                return;
            }

            if ((int) $targetHotspot->page->edition_id !== (int) $editionId) {
                $validator->errors()->add(
                    'target_hotspot_id',
                    'Selected target hotspot must belong to the same edition.',
                );

                return;
            }

            if (
                $targetPageNo !== null &&
                (int) $targetHotspot->page->page_no !== $targetPageNo
            ) {
                $validator->errors()->add(
                    'target_hotspot_id',
                    sprintf('Selected hotspot must belong to target page %d.', $targetPageNo),
                );
            }
        });
    }
}
