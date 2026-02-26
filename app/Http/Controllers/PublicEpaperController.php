<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Edition;
use App\Models\SiteSetting;
use App\Support\DiskUrl;
use App\Support\EpaperData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicEpaperController extends Controller
{
    public function index(Request $request): Response
    {
        $requestedDate = $this->normalizeDate($request->query('date'));
        $requestedEditionId = $this->editionIdFromQuery($request);
        $requestedPageNo = max(1, (int) $request->integer('page', 1));

        $featuredEdition = null;

        if ($requestedDate !== null) {
            $featuredEdition = $this->findPublishedEditionForDate(
                $requestedDate->toDateString(),
                $requestedEditionId,
            );
        }

        if ($featuredEdition === null && $requestedDate === null && $requestedEditionId !== null) {
            $featuredEdition = $this->baseEditionWithPagesQuery()
                ->whereKey($requestedEditionId)
                ->first();
        }

        if ($featuredEdition === null) {
            $todayDhaka = CarbonImmutable::now('Asia/Dhaka')->toDateString();

            $featuredEdition = $this->findPublishedEditionForDate($todayDhaka);
        }

        if ($featuredEdition === null) {
            $featuredEdition = $this->baseEditionWithPagesQuery()
                ->orderByDesc('edition_date')
                ->orderByDesc('id')
                ->first();
        }

        $currentPage = null;

        if ($featuredEdition !== null) {
            $pages = $featuredEdition->pages->values();

            $currentPage = $pages->firstWhere('page_no', $requestedPageNo);

            if ($currentPage === null) {
                $currentPage = $pages->first();
            }
        }

        $selectedDate = $featuredEdition?->edition_date->toDateString()
            ?? $requestedDate?->toDateString();

        $editionsForDate = $selectedDate !== null
            ? $this->editionsForDatePayload($selectedDate)
            : [];

        $rawSettings = EpaperData::mapSiteSettings(
            SiteSetting::query()->whereIn('key', SiteSetting::defaultKeys())->get(),
        );

        $logoPath = $rawSettings[SiteSetting::LOGO_PATH] ?? '';
        $logoUrl = $logoPath !== ''
            ? DiskUrl::fromPath((string) config('epaper.disk'), $logoPath)
            : null;

        return Inertia::render('Epaper/Index', [
            'edition' => $featuredEdition ? EpaperData::mapEdition($featuredEdition) : null,
            'selected_edition' => $featuredEdition ? $this->mapEditionOption($featuredEdition) : null,
            'editions_for_date' => $editionsForDate,
            'current_page' => $currentPage ? EpaperData::mapPage($currentPage) : null,
            'selected_page_no' => $currentPage?->page_no,
            'selected_date' => $selectedDate,
            'available_dates' => $this->availableDatesPayload(),
            'categories' => $this->categoriesPayload(),
            'logo_url' => $logoUrl,
            'settings' => [
                SiteSetting::FOOTER_EDITOR_INFO => $rawSettings[SiteSetting::FOOTER_EDITOR_INFO] ?? '',
                SiteSetting::FOOTER_CONTACT_INFO => $rawSettings[SiteSetting::FOOTER_CONTACT_INFO] ?? '',
                SiteSetting::FOOTER_COPYRIGHT => $rawSettings[SiteSetting::FOOTER_COPYRIGHT] ?? '',
            ],
        ]);
    }

    public function edition(string $date): Response
    {
        $editionDate = $this->normalizeDate($date);

        abort_if($editionDate === null, 404);

        $edition = Edition::query()
            ->published()
            ->whereDate('edition_date', $editionDate)
            ->orderByDesc('id')
            ->with([
                'pages' => fn ($query) => $query
                    ->with([
                        'category',
                        'hotspots' => fn ($hotspotQuery) => $hotspotQuery
                            ->with(['targetHotspot.page', 'linkedHotspot.page'])
                            ->orderBy('id'),
                    ])
                    ->orderBy('page_no'),
            ])
            ->firstOrFail();

        return Inertia::render('Epaper/Edition', [
            'edition' => EpaperData::mapEdition($edition),
            'adsBySlot' => $this->adsBySlot(),
            'settings' => EpaperData::mapSiteSettings(SiteSetting::query()->whereIn('key', SiteSetting::defaultKeys())->get()),
        ]);
    }

    public function viewer(Request $request, string $date, int $pageNo): Response
    {
        $editionDate = $this->normalizeDate($date);

        abort_if($editionDate === null, 404);

        $requestedEditionId = $this->editionIdFromQuery($request);

        $edition = $this->findPublishedEditionForDate(
            $editionDate->toDateString(),
            $requestedEditionId,
        );

        abort_if($edition === null, 404);

        $pages = $edition->pages->values();
        $page = $pages->firstWhere('page_no', $pageNo);

        abort_if($page === null, 404);

        $mappedPages = $pages
            ->map(function ($item): array {
                $mappedPage = EpaperData::mapPage($item);

                return [
                    'id' => $mappedPage['id'],
                    'edition_id' => $mappedPage['edition_id'],
                    'page_no' => $mappedPage['page_no'],
                    'category_id' => $mappedPage['category_id'],
                    'category_name' => $mappedPage['category_name'],
                    'image_thumb_url' => $mappedPage['image_thumb_url'],
                    'image_large_url' => $mappedPage['image_large_url'],
                    'image_original_url' => $mappedPage['image_original_url'],
                    'hotspots' => $mappedPage['hotspots'],
                ];
            })
            ->values()
            ->all();

        $currentIndex = $pages->search(fn ($item): bool => $item->id === $page->id);

        $prevPageNo = $currentIndex !== false && $currentIndex > 0
            ? $pages[$currentIndex - 1]->page_no
            : null;

        $nextPageNo = $currentIndex !== false && $currentIndex < ($pages->count() - 1)
            ? $pages[$currentIndex + 1]->page_no
            : null;

        $rawSettings = EpaperData::mapSiteSettings(
            SiteSetting::query()->whereIn('key', SiteSetting::defaultKeys())->get(),
        );
        $logoPath = $rawSettings[SiteSetting::LOGO_PATH] ?? '';
        $logoUrl = $logoPath !== ''
            ? DiskUrl::fromPath((string) config('epaper.disk'), $logoPath)
            : null;

        return Inertia::render('Epaper/Viewer', [
            'edition_date' => $edition->edition_date->toDateString(),
            'selected_edition' => $this->mapEditionOption($edition),
            'editions_for_date' => $this->editionsForDatePayload($edition->edition_date->toDateString()),
            'current_page_no' => $page->page_no,
            'page' => EpaperData::mapPage($page),
            'pages' => $mappedPages,
            'categories' => $this->categoriesPayload(),
            'available_dates' => $this->availableDatesPayload(),
            'prev_page_no' => $prevPageNo,
            'next_page_no' => $nextPageNo,
            'logo_url' => $logoUrl,
            'adsBySlot' => $this->adsBySlot(),
            'settings' => [
                SiteSetting::FOOTER_EDITOR_INFO => $rawSettings[SiteSetting::FOOTER_EDITOR_INFO] ?? '',
                SiteSetting::FOOTER_CONTACT_INFO => $rawSettings[SiteSetting::FOOTER_CONTACT_INFO] ?? '',
                SiteSetting::FOOTER_COPYRIGHT => $rawSettings[SiteSetting::FOOTER_COPYRIGHT] ?? '',
            ],
        ]);
    }

    /**
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function adsBySlot(): array
    {
        $ads = Ad::query()
            ->active()
            ->with('adSlot')
            ->orderBy('ad_slot_id')
            ->latest('id')
            ->get();

        $grouped = [];

        foreach ($ads as $ad) {
            $slotNo = $ad->adSlot?->slot_no;

            if ($slotNo === null) {
                continue;
            }

            $grouped[$slotNo] ??= [];
            $grouped[$slotNo][] = EpaperData::mapAd($ad);
        }

        foreach (range(1, 8) as $slot) {
            $grouped[$slot] ??= [];
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @return array<int, array{id:int,name:string|null,edition_date:string}>
     */
    private function editionsForDatePayload(string $date): array
    {
        return Edition::query()
            ->published()
            ->forDate($date)
            ->orderByDesc('id')
            ->get(['id', 'name', 'edition_date'])
            ->map(fn (Edition $edition): array => $this->mapEditionOption($edition))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id:int,name:string,position:int}>
     */
    private function categoriesPayload(): array
    {
        return Category::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'name', 'position'])
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'position' => $category->position,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function availableDatesPayload(): array
    {
        return Edition::query()
            ->published()
            ->orderByDesc('edition_date')
            ->orderByDesc('id')
            ->get(['edition_date'])
            ->map(fn (Edition $edition): string => $edition->edition_date->toDateString())
            ->unique()
            ->values()
            ->all();
    }

    private function editionIdFromQuery(Request $request): ?int
    {
        $value = $request->query('edition');
        $editionId = is_numeric($value) ? (int) $value : null;

        return $editionId !== null && $editionId > 0
            ? $editionId
            : null;
    }

    private function baseEditionWithPagesQuery(): Builder
    {
        return Edition::query()
            ->published()
            ->withCount('pages')
            ->with([
                'pages' => fn ($query) => $query
                    ->with([
                        'category',
                        'hotspots' => fn ($hotspotQuery) => $hotspotQuery
                            ->with(['targetHotspot.page', 'linkedHotspot.page'])
                            ->orderBy('id'),
                    ])
                    ->orderBy('page_no'),
            ]);
    }

    private function findPublishedEditionForDate(string $date, ?int $requestedEditionId = null): ?Edition
    {
        $query = $this->baseEditionWithPagesQuery()->forDate($date);

        if ($requestedEditionId !== null) {
            $selectedEdition = (clone $query)
                ->whereKey($requestedEditionId)
                ->first();

            if ($selectedEdition !== null) {
                return $selectedEdition;
            }
        }

        return $query
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{id:int,name:string|null,edition_date:string}
     */
    private function mapEditionOption(Edition $edition): array
    {
        return [
            'id' => $edition->id,
            'name' => $edition->name,
            'edition_date' => $edition->edition_date->toDateString(),
        ];
    }

    private function normalizeDate(mixed $date): ?CarbonImmutable
    {
        if (! is_string($date)) {
            return null;
        }

        $normalized = trim($date);

        if ($normalized === '') {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('Y-m-d', $normalized);

            if ($parsed === false || $parsed->format('Y-m-d') !== $normalized) {
                return null;
            }

            return $parsed->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
