<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EpAdmin\AdUpdateRequest;
use App\Models\Ad;
use App\Models\AdSlot;
use App\Support\DiskUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdController extends Controller
{
    public function index(): Response
    {
        foreach (range(1, 8) as $slotNo) {
            AdSlot::query()->firstOrCreate(
                ['slot_no' => $slotNo],
                ['title' => sprintf('Slot %d', $slotNo)],
            );
        }

        /** @var Collection<int, AdSlot> $slots */
        $slots = AdSlot::query()
            ->orderBy('slot_no')
            ->get();

        $slotIds = $slots->pluck('id')->all();

        /** @var Collection<int, Ad> $latestAds */
        $latestAds = Ad::query()
            ->whereIn('ad_slot_id', $slotIds)
            ->orderByDesc('id')
            ->get()
            ->unique('ad_slot_id')
            ->keyBy('ad_slot_id');

        $disk = (string) config('epaper.disk');

        return Inertia::render('EpAdmin/Placements/Index', [
            'slots' => $slots
                ->map(function (AdSlot $slot) use ($latestAds, $disk): array {
                    /** @var Ad|null $ad */
                    $ad = $latestAds->get($slot->id);

                    $imagePath = $ad?->image_path;
                    $imageUrl = $imagePath !== null && $imagePath !== ''
                        ? DiskUrl::fromPath($disk, $imagePath)
                        : ($ad?->image_url ?? '');

                    return [
                        'id' => $slot->id,
                        'slot_no' => $slot->slot_no,
                        'title' => $slot->title,
                        'ad' => [
                            'id' => $ad?->id,
                            'type' => $ad?->type ?? 'image',
                            'image_url' => $imageUrl,
                            'image_path' => $imagePath ?? '',
                            'click_url' => $ad?->click_url ?? '',
                            'embed_code' => $ad?->embed_code ?? '',
                            'is_active' => $ad?->is_active ?? true,
                            'starts_at' => $ad?->starts_at?->toISOString(),
                            'ends_at' => $ad?->ends_at?->toISOString(),
                        ],
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    public function update(AdUpdateRequest $request, int $slotNo): RedirectResponse
    {
        $slot = AdSlot::query()->firstOrCreate(
            ['slot_no' => $slotNo],
            ['title' => sprintf('Slot %d', $slotNo)],
        );

        $validated = $request->validated();
        $disk = Storage::disk(config('epaper.disk'));

        if (isset($validated['slot_title'])) {
            $slot->update(['title' => $this->nullableString($validated['slot_title']) ?? $slot->title]);
        }

        $latestAd = Ad::query()
            ->where('ad_slot_id', $slot->id)
            ->latest('id')
            ->first();

        $newImagePath = null;
        $newImageUrl = null;
        $removeImageFile = false;

        if ($validated['type'] === 'image') {
            $oldImagePath = $latestAd?->image_path;

            if ($request->hasFile('image_file')) {
                $storedPath = ltrim(
                    $request->file('image_file')->store(
                        sprintf('epaper/ads/%d', $slotNo),
                        config('epaper.disk'),
                    ),
                    '/',
                );
                $newImagePath = $storedPath;

                if ($oldImagePath !== null && $oldImagePath !== '' && $oldImagePath !== $storedPath && $disk->exists($oldImagePath)) {
                    $disk->delete($oldImagePath);
                }
            } elseif ($request->boolean('remove_image_file')) {
                $removeImageFile = true;

                if ($oldImagePath !== null && $oldImagePath !== '' && $disk->exists($oldImagePath)) {
                    $disk->delete($oldImagePath);
                }
            } else {
                $newImagePath = $oldImagePath;
                $newImageUrl = $this->nullableString($validated['image_url'] ?? null);
            }
        }

        $payload = [
            'type' => $validated['type'],
            'image_path' => $validated['type'] === 'image' && ! $removeImageFile ? $newImagePath : null,
            'image_url' => $validated['type'] === 'image' && $newImagePath === null && ! $removeImageFile ? $newImageUrl : null,
            'click_url' => $validated['type'] === 'image' ? $this->nullableString($validated['click_url'] ?? null) : null,
            'embed_code' => $validated['type'] === 'embed' ? $this->nullableString($validated['embed_code'] ?? null) : null,
            'is_active' => (bool) $validated['is_active'],
            'starts_at' => $this->nullableString($validated['starts_at'] ?? null),
            'ends_at' => $this->nullableString($validated['ends_at'] ?? null),
            'created_by' => (int) $request->user()->id,
        ];

        if ($latestAd === null) {
            $slot->ads()->create($payload);
        } else {
            $latestAd->update($payload);
        }

        return redirect()
            ->route('epadmin.ads.index')
            ->with('success', sprintf('Ad slot %d updated successfully.', $slotNo));
    }

    private function nullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
