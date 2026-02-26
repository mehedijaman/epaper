<?php

namespace App\Http\Requests\EpAdmin;

use Illuminate\Foundation\Http\FormRequest;

class PageUploadRequest extends FormRequest
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
        $maxFileSizeKb = max(1, (int) config('epaper.page_upload_max_file_kb', 15360));
        $maxFiles = max(1, (int) config('epaper.page_upload_max_files', 200));

        return [
            'edition_id' => ['required', 'integer', 'exists:editions,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'page_no_strategy' => ['nullable', 'string', 'in:auto,filename,next_available'],
            'files' => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'files.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maxFileSizeKb}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $uploadMax = (string) ini_get('upload_max_filesize');
        $postMax = (string) ini_get('post_max_size');
        $maxUploads = (string) ini_get('max_file_uploads');

        return [
            'files.required' => 'Please select at least one image.',
            'files.array' => 'Please upload images as a file list.',
            'files.min' => 'Please select at least one image.',
            'files.max' => sprintf(
                'Too many files selected for one upload. Server currently allows up to %s files per request.',
                $maxUploads,
            ),
            'files.*.uploaded' => sprintf(
                'One or more files failed to upload before validation. Current server limits: upload_max_filesize=%s, post_max_size=%s, max_file_uploads=%s.',
                $uploadMax,
                $postMax,
                $maxUploads,
            ),
        ];
    }
}
