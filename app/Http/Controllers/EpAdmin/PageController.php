<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EpAdmin\PageReorderRequest;
use App\Http\Requests\EpAdmin\PageReplaceRequest;
use App\Http\Requests\EpAdmin\PageUpdateRequest;
use App\Http\Requests\EpAdmin\PageUploadRequest;
use App\Models\Edition;
use App\Models\Page;
use App\Models\PageHotspot;
use App\Services\PageImageUploadService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PageController extends Controller
{
    public function upload(PageUploadRequest $request, PageImageUploadService $uploadService): RedirectResponse
    {
        $validated = $request->validated();

        $edition = Edition::query()->findOrFail((int) $validated['edition_id']);

        $this->authorize('update', $edition);

        $uploadedFiles = collect($validated['files'] ?? [])
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->values()
            ->all();
        $categoryId = array_key_exists('category_id', $validated) && $validated['category_id'] !== null
            ? (int) $validated['category_id']
            : null;

        if ($uploadedFiles === []) {
            throw ValidationException::withMessages([
                'files' => 'Please upload at least one valid image file.',
            ]);
        }

        $result = $uploadService->upload(
            edition: $edition,
            files: $uploadedFiles,
            pageNoStrategy: $validated['page_no_strategy'] ?? null,
            uploadedBy: $request->user()?->id,
            categoryId: $categoryId,
        );

        return redirect()
            ->route('epadmin.editions.manage', [
                'date' => $edition->edition_date->toDateString(),
                'edition_id' => $edition->id,
            ])
            ->with('success', sprintf('%d page(s) uploaded successfully.', $result['uploaded_count']))
            ->with('warnings', $result['warnings']);
    }

    public function reorder(PageReorderRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $edition = Edition::query()->findOrFail((int) $validated['edition_id']);
        $this->authorize('update', $edition);

        $orderedPageIds = collect($validated['ordered_page_ids'])
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        /** @var EloquentCollection<int, Page> $editionPages */
        $editionPages = Page::query()
            ->where('edition_id', $edition->id)
            ->orderBy('page_no')
            ->get(['id', 'page_no']);

        $existingPageIds = $editionPages->pluck('id')->sort()->values();
        $requestedPageIds = $orderedPageIds->sort()->values();

        if (
            $existingPageIds->count() !== $requestedPageIds->count()
            || $existingPageIds->all() !== $requestedPageIds->all()
        ) {
            throw ValidationException::withMessages([
                'ordered_page_ids' => 'Reorder payload is invalid for this edition.',
            ]);
        }

        $oldPageNoById = $editionPages->pluck('page_no', 'id');
        $tempStart = 65535 - $orderedPageIds->count();

        if ($tempStart < 1) {
            throw ValidationException::withMessages([
                'ordered_page_ids' => 'Too many pages to reorder.',
            ]);
        }

        DB::transaction(function () use ($orderedPageIds, $oldPageNoById, $edition, $tempStart): void {
            foreach ($orderedPageIds as $index => $pageId) {
                Page::query()
                    ->whereKey($pageId)
                    ->update(['page_no' => $tempStart + $index + 1]);
            }

            $oldToNewPageNo = [];

            foreach ($orderedPageIds as $index => $pageId) {
                $newPageNo = $index + 1;
                $oldPageNo = (int) ($oldPageNoById->get($pageId) ?? 0);

                Page::query()
                    ->whereKey($pageId)
                    ->update(['page_no' => $newPageNo]);

                if ($oldPageNo > 0) {
                    $oldToNewPageNo[$oldPageNo] = $newPageNo;
                }
            }

            if ($oldToNewPageNo === []) {
                return;
            }

            $editionPageIds = Page::query()
                ->where('edition_id', $edition->id)
                ->pluck('id');

            if ($editionPageIds->isEmpty()) {
                return;
            }

            $hotspots = PageHotspot::query()
                ->whereIn('page_id', $editionPageIds)
                ->whereIn('target_page_no', array_keys($oldToNewPageNo))
                ->get();

            foreach ($hotspots as $hotspot) {
                $newTarget = $oldToNewPageNo[(int) $hotspot->target_page_no] ?? null;

                if ($newTarget === null) {
                    continue;
                }

                $hotspot->target_page_no = $newTarget;
                $hotspot->save();
            }
        });

        return redirect()
            ->route('epadmin.editions.manage', [
                'date' => $edition->edition_date->toDateString(),
                'edition_id' => $edition->id,
            ])
            ->with('success', 'Pages reordered successfully.');
    }

    public function update(PageUpdateRequest $request, Page $page): RedirectResponse
    {
        $this->authorize('update', $page);

        $validated = $request->validated();
        $oldPageNo = $page->page_no;
        $newPageNo = (int) $validated['page_no'];
        $categoryId = array_key_exists('category_id', $validated) && $validated['category_id'] !== null
            ? (int) $validated['category_id']
            : null;

        DB::transaction(function () use ($page, $oldPageNo, $newPageNo, $categoryId): void {
            $page->update([
                'page_no' => $newPageNo,
                'category_id' => $categoryId,
            ]);

            if ($oldPageNo === $newPageNo) {
                return;
            }

            $editionPageIds = Page::query()
                ->where('edition_id', $page->edition_id)
                ->pluck('id');

            if ($editionPageIds->isEmpty()) {
                return;
            }

            PageHotspot::query()
                ->whereIn('page_id', $editionPageIds)
                ->where('target_page_no', $oldPageNo)
                ->update(['target_page_no' => $newPageNo]);
        });

        return redirect()
            ->route('epadmin.editions.manage', [
                'date' => $page->edition->edition_date->toDateString(),
                'edition_id' => $page->edition->id,
            ])
            ->with('success', sprintf('Page %d updated successfully.', $newPageNo));
    }

    public function replace(PageReplaceRequest $request, Page $page, PageImageUploadService $uploadService): RedirectResponse
    {
        $this->authorize('update', $page);

        $uploadedFile = $request->file('file');

        if (! $uploadedFile instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => 'Please choose a valid image file.',
            ]);
        }

        $uploadService->replace(
            page: $page,
            file: $uploadedFile,
            uploadedBy: $request->user()?->id,
        );

        return redirect()
            ->route('epadmin.editions.manage', [
                'date' => $page->edition->edition_date->toDateString(),
                'edition_id' => $page->edition->id,
            ])
            ->with('success', sprintf('Page %d image replaced successfully.', $page->page_no));
    }

    public function destroy(Page $page, PageImageUploadService $uploadService): RedirectResponse
    {
        $this->authorize('delete', $page);

        $page->loadMissing('edition');
        $editionDate = $page->edition->edition_date->toDateString();
        $pageNo = $page->page_no;

        DB::transaction(function () use ($page, $uploadService): void {
            $uploadService->deleteFiles($page);
            $page->delete();
        });

        return redirect()
            ->route('epadmin.editions.manage', [
                'date' => $editionDate,
                'edition_id' => $page->edition_id,
            ])
            ->with('success', sprintf('Page %d deleted successfully.', $pageNo));
    }
}
