<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Edition;
use App\Models\SiteSetting;
use App\Support\DiskUrl;
use App\Support\EpaperData;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicEpaperController extends Controller
{
    public function index(Request $request): Response
    {
        $requestedDate = $this->normalizeDate($request->query('date'));
        $requestedPageNo = max(1, (int) $request->integer('page', 1));

        $editionQuery = Edition::query()
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

        $featuredEdition = null;

        if ($requestedDate !== null) {
            $featuredEdition = (clone $editionQuery)
                ->forDate($requestedDate->toDateString())
                ->first();
        }

        if ($featuredEdition === null) {
            $todayDhaka = CarbonImmutable::now('Asia/Dhaka')->toDateString();

            $featuredEdition = (clone $editionQuery)
                ->forDate($todayDhaka)
                ->first();
        }

        if ($featuredEdition === null) {
            $featuredEdition = (clone $editionQuery)
                ->orderByDesc('edition_date')
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

        $availableDates = Edition::query()
            ->published()
            ->orderByDesc('edition_date')
            ->pluck('edition_date')
            ->map(fn (mixed $date): ?string => $this->toDateString($date))
            ->filter(fn (?string $date): bool => $date !== null)
            ->values()
            ->all();

        $rawSettings = EpaperData::mapSiteSettings(
            SiteSetting::query()->whereIn('key', SiteSetting::defaultKeys())->get(),
        );

        $logoPath = $rawSettings[SiteSetting::LOGO_PATH] ?? '';
        $logoUrl = $logoPath !== ''
            ? DiskUrl::fromPath((string) config('epaper.disk'), $logoPath)
            : null;

        return Inertia::render('Epaper/Index', [
            'edition' => $featuredEdition ? EpaperData::mapEdition($featuredEdition) : null,
            'current_page' => $currentPage ? EpaperData::mapPage($currentPage) : null,
            'selected_page_no' => $currentPage?->page_no,
            'selected_date' => $featuredEdition?->edition_date->toDateString(),
            'available_dates' => $availableDates,
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

    public function viewer(string $date, int $pageNo): Response
    {
        $editionDate = $this->normalizeDate($date);

        abort_if($editionDate === null, 404);

        $edition = Edition::query()
            ->published()
            ->whereDate('edition_date', $editionDate)
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

        $categories = $pages
            ->map(fn ($item) => $item->category)
            ->filter()
            ->unique('id')
            ->sortBy('position')
            ->values()
            ->map(fn ($category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'position' => $category->position,
            ])
            ->all();

        $availableDates = Edition::query()
            ->published()
            ->orderByDesc('edition_date')
            ->pluck('edition_date')
            ->map(fn (mixed $value): ?string => $this->toDateString($value))
            ->filter(fn (?string $value): bool => $value !== null)
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
            'current_page_no' => $page->page_no,
            'page' => EpaperData::mapPage($page),
            'pages' => $mappedPages,
            'categories' => $categories,
            'available_dates' => $availableDates,
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

    private function toDateString(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
