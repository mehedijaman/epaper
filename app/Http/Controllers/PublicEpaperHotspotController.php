<?php

namespace App\Http\Controllers;

use App\Models\Edition;
use App\Models\Page;
use App\Models\PageHotspot;
use App\Models\SiteSetting;
use App\Support\DiskUrl;
use App\Support\EpaperData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicEpaperHotspotController extends Controller
{
    public function show(Request $request, string $date, int $pageNo, int $hotspotId): Response
    {
        $context = $this->resolveHotspotContext($request, $date, $pageNo, $hotspotId);

        abort_if($context === null, 404);

        /** @var Edition $edition */
        $edition = $context['edition'];
        /** @var Page $page */
        $page = $context['page'];
        /** @var PageHotspot $hotspot */
        $hotspot = $context['hotspot'];

        $editionDate = $edition->edition_date->toDateString();
        $viewerUrl = route('epaper.viewer', [
            'date' => $editionDate,
            'pageNo' => $page->page_no,
            'edition' => $edition->id,
        ]);

        $targetExists = Page::query()
            ->where('edition_id', $edition->id)
            ->where('page_no', $hotspot->target_page_no)
            ->exists();

        $targetUrl = $targetExists
            ? route('epaper.viewer', [
                'date' => $editionDate,
                'pageNo' => $hotspot->target_page_no,
                'edition' => $edition->id,
            ])
            : null;

        $previewUrl = route('epaper.hotspot.preview', [
            'date' => $editionDate,
            'pageNo' => $page->page_no,
            'hotspotId' => $hotspot->id,
            'edition' => $edition->id,
        ]);

        $targetHotspot = $this->resolveTargetHotspot($hotspot, $edition->id);
        $targetPreviewUrl = $targetHotspot !== null
            ? route('epaper.hotspot.target-preview', [
                'date' => $editionDate,
                'pageNo' => $page->page_no,
                'hotspotId' => $hotspot->id,
                'edition' => $edition->id,
            ])
            : null;

        $rawSettings = EpaperData::mapSiteSettings(
            SiteSetting::query()->whereIn('key', SiteSetting::defaultKeys())->get(),
        );
        $logoPath = $rawSettings[SiteSetting::LOGO_PATH] ?? '';
        $logoUrl = $logoPath !== ''
            ? DiskUrl::fromPath((string) config('epaper.disk'), $logoPath)
            : null;

        return Inertia::render('Epaper/Hotspot', [
            'date' => $editionDate,
            'pageNo' => $page->page_no,
            'viewerUrl' => $viewerUrl,
            'targetUrl' => $targetUrl,
            'previewUrl' => $previewUrl,
            'targetPreviewUrl' => $targetPreviewUrl,
            'logoUrl' => $logoUrl,
            'settings' => [
                SiteSetting::FOOTER_EDITOR_INFO => $rawSettings[SiteSetting::FOOTER_EDITOR_INFO] ?? '',
                SiteSetting::FOOTER_CONTACT_INFO => $rawSettings[SiteSetting::FOOTER_CONTACT_INFO] ?? '',
                SiteSetting::FOOTER_COPYRIGHT => $rawSettings[SiteSetting::FOOTER_COPYRIGHT] ?? '',
            ],
            'hotspot' => $this->serializeHotspot($hotspot),
            'targetHotspot' => $targetHotspot !== null
                ? $this->serializeHotspot($targetHotspot)
                : null,
        ]);
    }

    public function preview(Request $request, string $date, int $pageNo, int $hotspotId): StreamedResponse
    {
        $context = $this->resolveHotspotContext($request, $date, $pageNo, $hotspotId);

        abort_if($context === null, 404);

        /** @var Edition $edition */
        $edition = $context['edition'];
        /** @var Page $page */
        $page = $context['page'];
        /** @var PageHotspot $hotspot */
        $hotspot = $context['hotspot'];

        return $this->streamHotspotPreview($edition, $page, $hotspot);
    }

    public function targetPreview(Request $request, string $date, int $pageNo, int $hotspotId): StreamedResponse
    {
        $context = $this->resolveHotspotContext($request, $date, $pageNo, $hotspotId);

        abort_if($context === null, 404);

        /** @var Edition $edition */
        $edition = $context['edition'];
        /** @var PageHotspot $hotspot */
        $hotspot = $context['hotspot'];

        $targetHotspot = $this->resolveTargetHotspot($hotspot, $edition->id);

        abort_if($targetHotspot === null, 404);

        /** @var Page|null $targetPage */
        $targetPage = $targetHotspot->page;

        abort_if($targetPage === null, 404);

        return $this->streamHotspotPreview($edition, $targetPage, $targetHotspot);
    }

    private function streamHotspotPreview(Edition $edition, Page $page, PageHotspot $hotspot): StreamedResponse
    {
        $disk = Storage::disk((string) config('epaper.disk'));
        $sourcePath = (string) ($page->image_large_path ?: $page->image_original_path);

        abort_if($sourcePath === '' || ! $disk->exists($sourcePath), 404);

        $cacheKey = sha1(implode('|', [
            $sourcePath,
            $hotspot->id,
            round((float) $hotspot->x, 6),
            round((float) $hotspot->y, 6),
            round((float) $hotspot->w, 6),
            round((float) $hotspot->h, 6),
            $page->updated_at?->getTimestamp() ?? 0,
            $hotspot->updated_at?->getTimestamp() ?? 0,
        ]));

        $cachedPath = sprintf(
            'epaper/hotspot-previews/%s/%s.jpg',
            $edition->edition_date->toDateString(),
            $cacheKey,
        );

        if (! $disk->exists($cachedPath)) {
            try {
                $image = Image::read($disk->get($sourcePath));
            } catch (\Throwable) {
                abort(404);
            }

            $imageWidth = $image->width();
            $imageHeight = $image->height();

            abort_if($imageWidth <= 0 || $imageHeight <= 0, 404);

            $xRatio = max(0.0, min(1.0, (float) $hotspot->x));
            $yRatio = max(0.0, min(1.0, (float) $hotspot->y));
            $wRatio = max(0.0001, min(1.0, (float) $hotspot->w));
            $hRatio = max(0.0001, min(1.0, (float) $hotspot->h));

            $cropX = max(0, min($imageWidth - 1, (int) floor($xRatio * $imageWidth)));
            $cropY = max(0, min($imageHeight - 1, (int) floor($yRatio * $imageHeight)));

            $cropWidth = max(1, (int) round($wRatio * $imageWidth));
            $cropHeight = max(1, (int) round($hRatio * $imageHeight));

            $cropWidth = min($cropWidth, $imageWidth - $cropX);
            $cropHeight = min($cropHeight, $imageHeight - $cropY);

            $cropWidth = max(1, $cropWidth);
            $cropHeight = max(1, $cropHeight);

            $preview = $image
                ->crop($cropWidth, $cropHeight, $cropX, $cropY)
                ->scaleDown(width: 1600);

            $disk->put($cachedPath, (string) $preview->toJpeg(quality: 85));
        }

        return $disk->response(
            $cachedPath,
            null,
            [
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Content-Type' => 'image/jpeg',
            ],
        );
    }

    private function resolveTargetHotspot(PageHotspot $hotspot, int $editionId): ?PageHotspot
    {
        if ($hotspot->target_hotspot_id !== null) {
            $directTarget = PageHotspot::query()
                ->with(['page:id,edition_id,image_original_path,image_large_path'])
                ->whereKey($hotspot->target_hotspot_id)
                ->whereHas('page', fn ($query) => $query->where('edition_id', $editionId))
                ->first();

            if ($directTarget !== null) {
                return $directTarget;
            }
        }

        return PageHotspot::query()
            ->with(['page:id,edition_id,image_original_path,image_large_path'])
            ->where('linked_hotspot_id', $hotspot->id)
            ->whereHas('page', fn ($query) => $query->where('edition_id', $editionId))
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array{id: int, x: float, y: float, w: float, h: float, label: string|null, relation_kind: string, target_page_no: int}
     */
    private function serializeHotspot(PageHotspot $hotspot): array
    {
        return [
            'id' => $hotspot->id,
            'x' => round((float) $hotspot->x, 6),
            'y' => round((float) $hotspot->y, 6),
            'w' => round((float) $hotspot->w, 6),
            'h' => round((float) $hotspot->h, 6),
            'label' => $hotspot->label,
            'relation_kind' => $hotspot->relation_kind ?? 'next',
            'target_page_no' => $hotspot->target_page_no ?? 1,
        ];
    }

    /**
     * @return array{edition: Edition, page: Page, hotspot: PageHotspot}|null
     */
    private function resolveHotspotContext(
        Request $request,
        string $date,
        int $pageNo,
        int $hotspotId,
    ): ?array {
        $editionDate = $this->normalizeDate($date);

        if ($editionDate === null) {
            return null;
        }

        $edition = $this->resolvePublishedEdition(
            $editionDate->toDateString(),
            $this->editionIdFromQuery($request),
        );

        if ($edition === null) {
            return null;
        }

        $page = Page::query()
            ->where('edition_id', $edition->id)
            ->where('page_no', $pageNo)
            ->first();

        if ($page === null) {
            return null;
        }

        $hotspot = $page->hotspots()
            ->whereKey($hotspotId)
            ->first();

        if ($hotspot === null) {
            return null;
        }

        return [
            'edition' => $edition,
            'page' => $page,
            'hotspot' => $hotspot,
        ];
    }

    private function resolvePublishedEdition(string $date, ?int $requestedEditionId): ?Edition
    {
        $query = Edition::query()
            ->published()
            ->forDate($date);

        if ($requestedEditionId !== null) {
            $selected = (clone $query)
                ->whereKey($requestedEditionId)
                ->first();

            if ($selected !== null) {
                return $selected;
            }
        }

        return $query
            ->orderByDesc('id')
            ->first();
    }

    private function editionIdFromQuery(Request $request): ?int
    {
        $value = $request->query('edition');

        if (! is_numeric($value)) {
            return null;
        }

        $editionId = (int) $value;

        return $editionId > 0 ? $editionId : null;
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
