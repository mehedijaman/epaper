<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EpAdmin\HotspotBulkDestroyRequest;
use App\Http\Requests\EpAdmin\HotspotStoreRequest;
use App\Http\Requests\EpAdmin\HotspotUpdateRequest;
use App\Models\Page;
use App\Models\PageHotspot;
use App\Support\EpaperData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PageHotspotController extends Controller
{
    public function index(Request $request): Response
    {
        $selectedPage = $this->resolvePage($request);
        $focusHotspotId = $this->resolveFocusHotspotId($request);

        if ($selectedPage === null) {
            return Inertia::render('EpAdmin/Pages/Map', [
                'selected_page_id' => null,
                'edition' => null,
                'page' => null,
                'edition_pages' => [],
                'target_page_numbers' => [],
                'target_hotspots_by_page' => [],
                'focus_hotspot_id' => null,
            ]);
        }

        /** @var Collection<int, Page> $editionPages */
        $editionPages = Page::query()
            ->where('edition_id', $selectedPage->edition_id)
            ->with([
                'hotspots' => fn ($query) => $query->orderBy('id'),
            ])
            ->orderBy('page_no')
            ->get(['id', 'page_no']);

        $maxPageNo = (int) $editionPages->max('page_no');

        return Inertia::render('EpAdmin/Pages/Map', [
            'selected_page_id' => $selectedPage->id,
            'edition' => [
                'id' => $selectedPage->edition->id,
                'edition_date' => $selectedPage->edition->edition_date->toDateString(),
                'name' => $selectedPage->edition->name,
                'status' => $selectedPage->edition->status,
                'published_at' => $selectedPage->edition->published_at?->toISOString(),
                'pages_count' => $editionPages->count(),
                'max_page_no' => $maxPageNo,
            ],
            'page' => EpaperData::mapPage($selectedPage),
            'edition_pages' => $editionPages
                ->map(fn (Page $page): array => [
                    'id' => $page->id,
                    'page_no' => $page->page_no,
                ])
                ->values()
                ->all(),
            'target_page_numbers' => $editionPages
                ->pluck('page_no')
                ->map(fn (mixed $pageNo): int => (int) $pageNo)
                ->values()
                ->all(),
            'target_hotspots_by_page' => $editionPages
                ->mapWithKeys(fn (Page $page): array => [
                    (string) $page->page_no => $page->hotspots
                        ->map(fn (PageHotspot $hotspot): array => [
                            'id' => $hotspot->id,
                            'relation_kind' => $hotspot->relation_kind ?? 'next',
                            'target_page_no' => $hotspot->target_page_no !== null
                                ? (int) $hotspot->target_page_no
                                : null,
                            'label' => $hotspot->label,
                        ])
                        ->values()
                        ->all(),
                ])
                ->all(),
            'focus_hotspot_id' => $focusHotspotId,
        ]);
    }

    public function store(HotspotStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $page = Page::query()->findOrFail((int) $validated['page_id']);
        $targetHotspotId = $this->parseTargetHotspotId($validated);
        $targetPageNo = $this->parseTargetPageNo($validated, $targetHotspotId);

        $this->authorize('create', PageHotspot::class);

        DB::transaction(function () use ($page, $validated, $targetHotspotId, $targetPageNo, $request): void {
            $hotspot = $page->hotspots()->create([
                'type' => 'relation',
                'relation_kind' => $validated['relation_kind'],
                'target_page_no' => $targetPageNo,
                'target_hotspot_id' => null,
                'linked_hotspot_id' => null,
                'x' => round((float) $validated['x'], 6),
                'y' => round((float) $validated['y'], 6),
                'w' => round((float) $validated['w'], 6),
                'h' => round((float) $validated['h'], 6),
                'label' => isset($validated['label']) && trim((string) $validated['label']) !== ''
                    ? trim((string) $validated['label'])
                    : null,
                'created_by' => (int) $request->user()->id,
            ]);

            $this->syncLinkedHotspot($hotspot, $targetHotspotId);
        });

        return redirect()
            ->route('epadmin.hotspots.index', ['page_id' => $page->id])
            ->with('success', 'Hotspot created successfully.');
    }

    public function update(HotspotUpdateRequest $request, PageHotspot $hotspot): RedirectResponse
    {
        $validated = $request->validated();
        $targetHotspotId = $this->parseTargetHotspotId($validated);
        $targetPageNo = $this->parseTargetPageNo($validated, $targetHotspotId);
        $this->authorize('update', $hotspot);

        DB::transaction(function () use ($hotspot, $validated, $targetHotspotId, $targetPageNo): void {
            $hotspot->update([
                'relation_kind' => $validated['relation_kind'],
                'target_page_no' => $targetPageNo,
                'x' => round((float) $validated['x'], 6),
                'y' => round((float) $validated['y'], 6),
                'w' => round((float) $validated['w'], 6),
                'h' => round((float) $validated['h'], 6),
                'label' => isset($validated['label']) && trim((string) $validated['label']) !== ''
                    ? trim((string) $validated['label'])
                    : null,
            ]);

            $this->syncLinkedHotspot($hotspot, $targetHotspotId);
        });

        return redirect()
            ->route('epadmin.hotspots.index', ['page_id' => $hotspot->page_id])
            ->with('success', 'Hotspot updated successfully.');
    }

    public function destroy(PageHotspot $hotspot): RedirectResponse
    {
        $this->authorize('delete', $hotspot);
        $pageId = $hotspot->page_id;

        DB::transaction(function () use ($hotspot): void {
            $this->deleteHotspotAndDetachLinks($hotspot);
        });

        return redirect()
            ->route('epadmin.hotspots.index', ['page_id' => $pageId])
            ->with('success', 'Hotspot deleted successfully.');
    }

    public function bulkDestroy(HotspotBulkDestroyRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $pageId = (int) $validated['page_id'];
        $hotspotIds = collect($validated['hotspot_ids'])
            ->map(fn (mixed $value): int => (int) $value)
            ->values();

        /** @var Collection<int, PageHotspot> $hotspots */
        $hotspots = PageHotspot::query()
            ->where('page_id', $pageId)
            ->whereIn('id', $hotspotIds)
            ->orderBy('id')
            ->get();

        if ($hotspots->count() !== $hotspotIds->count()) {
            throw ValidationException::withMessages([
                'hotspot_ids' => 'Some selected hotspots are no longer available on this page.',
            ]);
        }

        foreach ($hotspots as $hotspot) {
            $this->authorize('delete', $hotspot);
        }

        DB::transaction(function () use ($hotspots): void {
            foreach ($hotspots as $hotspot) {
                $this->deleteHotspotAndDetachLinks($hotspot);
            }
        });

        return redirect()
            ->route('epadmin.hotspots.index', ['page_id' => $pageId])
            ->with('success', sprintf('%d hotspot(s) deleted successfully.', $hotspots->count()));
    }

    private function resolvePage(Request $request): ?Page
    {
        $rawPageId = $request->query('page_id');
        $pageId = is_numeric($rawPageId) ? (int) $rawPageId : null;

        if ($pageId !== null && $pageId > 0) {
            $page = Page::query()
                ->with([
                    'edition',
                    'category',
                    'hotspots' => fn ($query) => $query->orderBy('id'),
                ])
                ->find($pageId);

            if ($page !== null) {
                return $page;
            }
        }

        return Page::query()
            ->with([
                'edition',
                'category',
                'hotspots' => fn ($query) => $query->orderBy('id'),
            ])
            ->latest('id')
            ->first();
    }

    private function resolveFocusHotspotId(Request $request): ?int
    {
        $rawHotspotId = $request->query('focus_hotspot_id');

        if (! is_numeric($rawHotspotId)) {
            return null;
        }

        $hotspotId = (int) $rawHotspotId;

        return $hotspotId > 0 ? $hotspotId : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function parseTargetHotspotId(array $validated): ?int
    {
        if (! array_key_exists('target_hotspot_id', $validated)) {
            return null;
        }

        $value = $validated['target_hotspot_id'];

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function parseTargetPageNo(array $validated, ?int $targetHotspotId): ?int
    {
        $value = $validated['target_page_no'] ?? null;

        if (is_numeric($value)) {
            $pageNo = (int) $value;

            if ($pageNo > 0) {
                return $pageNo;
            }
        }

        if ($targetHotspotId === null) {
            return null;
        }

        $resolvedPageNo = PageHotspot::query()
            ->whereKey($targetHotspotId)
            ->join('pages', 'pages.id', '=', 'page_hotspots.page_id')
            ->value('pages.page_no');

        return is_numeric($resolvedPageNo) ? (int) $resolvedPageNo : null;
    }

    private function syncLinkedHotspot(PageHotspot $sourceHotspot, ?int $targetHotspotId): void
    {
        $sourceId = $sourceHotspot->id;
        $previousTargetHotspotId = $sourceHotspot->target_hotspot_id;

        // Detach the previous target backlink if the source changed its target.
        if ($previousTargetHotspotId !== null && $previousTargetHotspotId !== $targetHotspotId) {
            PageHotspot::query()
                ->whereKey($previousTargetHotspotId)
                ->where('linked_hotspot_id', $sourceId)
                ->update(['linked_hotspot_id' => null]);
        }

        // Keep only one reverse backlink to this source hotspot.
        $backlinkCleanupQuery = PageHotspot::query()
            ->where('linked_hotspot_id', $sourceId);

        if ($targetHotspotId !== null) {
            $backlinkCleanupQuery->where('id', '!=', $targetHotspotId);
        }

        $backlinkCleanupQuery->update(['linked_hotspot_id' => null]);

        if ($targetHotspotId === null) {
            $sourceHotspot->target_hotspot_id = null;
            $sourceHotspot->linked_hotspot_id = null;
            $sourceHotspot->save();

            return;
        }

        // Enforce one-to-one mapping: no other source can point to the same target.
        PageHotspot::query()
            ->where('target_hotspot_id', $targetHotspotId)
            ->where('id', '!=', $sourceId)
            ->update([
                'target_hotspot_id' => null,
                'linked_hotspot_id' => null,
            ]);

        // If target currently points back to another source, detach that source.
        $targetHotspot = PageHotspot::query()
            ->select(['id', 'linked_hotspot_id'])
            ->find($targetHotspotId);

        if (
            $targetHotspot !== null
            && $targetHotspot->linked_hotspot_id !== null
            && $targetHotspot->linked_hotspot_id !== $sourceId
        ) {
            PageHotspot::query()
                ->whereKey($targetHotspot->linked_hotspot_id)
                ->where('target_hotspot_id', $targetHotspotId)
                ->update([
                    'target_hotspot_id' => null,
                    'linked_hotspot_id' => null,
                ]);
        }

        $sourceHotspot->target_hotspot_id = $targetHotspotId;
        $sourceHotspot->linked_hotspot_id = $targetHotspotId;
        $sourceHotspot->save();

        PageHotspot::query()
            ->whereKey($targetHotspotId)
            ->update(['linked_hotspot_id' => $sourceId]);
    }

    private function deleteHotspotAndDetachLinks(PageHotspot $hotspot): void
    {
        $hotspotId = $hotspot->id;
        $targetHotspotId = $hotspot->target_hotspot_id;

        if ($targetHotspotId !== null) {
            PageHotspot::query()
                ->whereKey($targetHotspotId)
                ->where('linked_hotspot_id', $hotspotId)
                ->update(['linked_hotspot_id' => null]);
        }

        PageHotspot::query()
            ->where('target_hotspot_id', $hotspotId)
            ->update(['target_hotspot_id' => null, 'linked_hotspot_id' => null]);

        PageHotspot::query()
            ->where('linked_hotspot_id', $hotspotId)
            ->update(['linked_hotspot_id' => null]);

        $hotspot->delete();
    }
}
