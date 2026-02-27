<?php

use App\Models\Edition;
use App\Models\Page;
use App\Models\PageHotspot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('hotspot detail page is accessible for published edition hotspot', function () {
    $user = User::factory()->create();

    $edition = Edition::query()->create([
        'edition_date' => '2026-02-20',
        'status' => Edition::STATUS_PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
    ]);

    $pageOne = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/original/page-0001.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page-0001.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0001.jpg',
        'width' => 2100,
        'height' => 2800,
        'uploaded_by' => $user->id,
    ]);

    $pageTwo = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 2,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/original/page-0002.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page-0002.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0002.jpg',
        'width' => 2100,
        'height' => 2800,
        'uploaded_by' => $user->id,
    ]);

    $hotspot = PageHotspot::query()->create([
        'page_id' => $pageOne->id,
        'type' => 'relation',
        'relation_kind' => 'next',
        'target_page_no' => 2,
        'x' => 0.1,
        'y' => 0.2,
        'w' => 0.3,
        'h' => 0.4,
        'label' => 'Lead Story',
        'created_by' => $user->id,
    ]);

    $targetHotspot = PageHotspot::query()->create([
        'page_id' => $pageTwo->id,
        'type' => 'relation',
        'relation_kind' => 'previous',
        'target_page_no' => 1,
        'x' => 0.2,
        'y' => 0.3,
        'w' => 0.1,
        'h' => 0.2,
        'label' => 'Target Story',
        'created_by' => $user->id,
    ]);

    $hotspot->target_hotspot_id = $targetHotspot->id;
    $hotspot->linked_hotspot_id = $targetHotspot->id;
    $hotspot->save();

    $this->get(route('epaper.hotspot.show', [
        'date' => '2026-02-20',
        'pageNo' => 1,
        'hotspotId' => $hotspot->id,
        'edition' => $edition->id,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Epaper/Hotspot')
            ->where('date', '2026-02-20')
            ->where('pageNo', 1)
            ->where('hotspot.id', $hotspot->id)
            ->where('hotspot.label', 'Lead Story')
            ->where('hotspot.relation_kind', 'next')
            ->where('hotspot.target_page_no', 2)
            ->where('viewerUrl', route('epaper.viewer', [
                'date' => '2026-02-20',
                'pageNo' => 1,
                'edition' => $edition->id,
            ]))
            ->where('targetUrl', route('epaper.viewer', [
                'date' => '2026-02-20',
                'pageNo' => 2,
                'edition' => $edition->id,
            ]))
            ->where('previewUrl', route('epaper.hotspot.preview', [
                'date' => '2026-02-20',
                'pageNo' => 1,
                'hotspotId' => $hotspot->id,
                'edition' => $edition->id,
            ]))
            ->where('targetHotspot.id', $targetHotspot->id)
            ->where('targetHotspot.label', 'Target Story')
            ->where('targetPreviewUrl', route('epaper.hotspot.target-preview', [
                'date' => '2026-02-20',
                'pageNo' => 1,
                'hotspotId' => $hotspot->id,
                'edition' => $edition->id,
            ])),
        );
});

test('hotspot preview returns cropped image and validates page ownership', function () {
    $diskName = (string) config('epaper.disk');
    Storage::fake($diskName);

    $user = User::factory()->create();

    $edition = Edition::query()->create([
        'edition_date' => '2026-02-20',
        'status' => Edition::STATUS_PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
    ]);

    $fakeImage = UploadedFile::fake()->image('page.jpg', 2200, 3200);
    Storage::disk($diskName)->putFileAs('epaper/2026-02-20/large', $fakeImage, 'page.jpg');

    $pageOne = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/large/page.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0001.jpg',
        'width' => 2200,
        'height' => 3200,
        'uploaded_by' => $user->id,
    ]);

    $pageTwo = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 2,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/large/page.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0002.jpg',
        'width' => 2200,
        'height' => 3200,
        'uploaded_by' => $user->id,
    ]);

    $hotspotOnPageOne = PageHotspot::query()->create([
        'page_id' => $pageOne->id,
        'type' => 'relation',
        'relation_kind' => 'next',
        'target_page_no' => 2,
        'x' => 0.12,
        'y' => 0.22,
        'w' => 0.34,
        'h' => 0.45,
        'label' => null,
        'created_by' => $user->id,
    ]);

    $hotspotOnPageTwo = PageHotspot::query()->create([
        'page_id' => $pageTwo->id,
        'type' => 'relation',
        'relation_kind' => 'previous',
        'target_page_no' => 1,
        'x' => 0.1,
        'y' => 0.1,
        'w' => 0.2,
        'h' => 0.2,
        'label' => null,
        'created_by' => $user->id,
    ]);

    $hotspotOnPageOne->target_hotspot_id = $hotspotOnPageTwo->id;
    $hotspotOnPageOne->linked_hotspot_id = $hotspotOnPageTwo->id;
    $hotspotOnPageOne->save();

    $this->get(route('epaper.hotspot.preview', [
        'date' => '2026-02-20',
        'pageNo' => 1,
        'hotspotId' => $hotspotOnPageOne->id,
        'edition' => $edition->id,
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');

    $this->get(route('epaper.hotspot.preview', [
        'date' => '2026-02-20',
        'pageNo' => 1,
        'hotspotId' => $hotspotOnPageTwo->id,
        'edition' => $edition->id,
    ]))
        ->assertNotFound();

    $this->get(route('epaper.hotspot.target-preview', [
        'date' => '2026-02-20',
        'pageNo' => 1,
        'hotspotId' => $hotspotOnPageOne->id,
        'edition' => $edition->id,
    ]))
        ->assertOk()
        ->assertHeader('content-type', 'image/jpeg');
});
