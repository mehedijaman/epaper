<?php

namespace App\Support;

use App\Models\Ad;
use App\Models\Edition;
use App\Models\Page;
use App\Models\PageHotspot;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

class EpaperData
{
    /**
     * @return array<string, float|int|string|null>|null
     */
    private static function mapLinkedHotspot(?PageHotspot $hotspot): ?array
    {
        if ($hotspot === null) {
            return null;
        }

        $linkedPageNo = $hotspot->relationLoaded('page')
            ? $hotspot->page?->page_no
            : null;
        $targetPageNo = $hotspot->target_page_no !== null
            ? (int) $hotspot->target_page_no
            : null;
        $resolvedPageNo = $linkedPageNo !== null
            ? (int) $linkedPageNo
            : $targetPageNo;

        return [
            'id' => $hotspot->id,
            'page_no' => $resolvedPageNo,
            'relation_kind' => $hotspot->relation_kind ?? 'next',
            'target_page_no' => $targetPageNo,
            'x' => round((float) $hotspot->x, 6),
            'y' => round((float) $hotspot->y, 6),
            'w' => round((float) $hotspot->w, 6),
            'h' => round((float) $hotspot->h, 6),
            'label' => $hotspot->label,
        ];
    }

    /**
     * @param  Collection<int, PageHotspot>  $hotspots
     * @return array<int, array<string, mixed>>
     */
    public static function mapHotspots(Collection $hotspots): array
    {
        return $hotspots
            ->map(
                fn (PageHotspot $hotspot): array => [
                    'id' => $hotspot->id,
                    'relation_kind' => $hotspot->relation_kind ?? 'next',
                    'target_page_no' => $hotspot->target_page_no !== null
                        ? (int) $hotspot->target_page_no
                        : null,
                    'target_hotspot_id' => $hotspot->target_hotspot_id,
                    'linked_hotspot_id' => $hotspot->linked_hotspot_id,
                    'x' => round((float) $hotspot->x, 6),
                    'y' => round((float) $hotspot->y, 6),
                    'w' => round((float) $hotspot->w, 6),
                    'h' => round((float) $hotspot->h, 6),
                    'label' => $hotspot->label,
                    'target_hotspot' => $hotspot->relationLoaded('targetHotspot')
                        ? self::mapLinkedHotspot($hotspot->targetHotspot)
                        : null,
                    'linked_hotspot' => $hotspot->relationLoaded('linkedHotspot')
                        ? self::mapLinkedHotspot($hotspot->linkedHotspot)
                        : null,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapPage(Page $page): array
    {
        $disk = (string) config('epaper.disk');
        $originalPath = (string) $page->image_original_path;
        $largePath = $page->image_large_path ?: $originalPath;
        $thumbPath = $page->image_thumb_path ?: $originalPath;

        return [
            'id' => $page->id,
            'edition_id' => $page->edition_id,
            'page_no' => $page->page_no,
            'category_id' => $page->category_id,
            'category_name' => $page->category?->name,
            'width' => $page->width,
            'height' => $page->height,
            'image_original_path' => $originalPath,
            'image_large_path' => $page->image_large_path,
            'image_thumb_path' => $page->image_thumb_path,
            'image_original_url' => DiskUrl::fromPath($disk, $originalPath),
            'image_large_url' => DiskUrl::fromPath($disk, $largePath),
            'image_thumb_url' => DiskUrl::fromPath($disk, $thumbPath),
            'hotspots' => self::mapHotspots($page->hotspots),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapEdition(Edition $edition): array
    {
        return [
            'id' => $edition->id,
            'edition_date' => $edition->edition_date->toDateString(),
            'name' => $edition->name,
            'status' => $edition->status,
            'published_at' => $edition->published_at?->toISOString(),
            'pages' => $edition->pages->map(fn (Page $page): array => self::mapPage($page))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mapAd(Ad $ad): array
    {
        return [
            'id' => $ad->id,
            'ad_slot_id' => $ad->ad_slot_id,
            'slot_no' => $ad->adSlot?->slot_no,
            'slot_name' => $ad->adSlot?->name,
            'type' => $ad->type,
            'image_url' => $ad->image_url,
            'click_url' => $ad->click_url,
            'embed_code' => $ad->embed_code,
            'is_active' => $ad->is_active,
            'starts_at' => $ad->starts_at?->toISOString(),
            'ends_at' => $ad->ends_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mapSiteSettings(Collection $settings): array
    {
        $pairs = $settings
            ->pluck('value', 'key')
            ->toArray();

        $result = [];

        foreach (SiteSetting::defaultKeys() as $key) {
            $result[$key] = (string) ($pairs[$key] ?? '');
        }

        return $result;
    }
}
