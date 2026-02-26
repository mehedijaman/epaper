<?php

use App\Models\Category;
use App\Models\Edition;
use App\Models\Page;
use App\Models\PageHotspot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

test('/epaper redirects to root homepage', function () {
    $this->get('/epaper')->assertRedirect('/');
});

test('homepage prefers today published edition in Asia/Dhaka', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 2, 20, 9, 0, 0, 'Asia/Dhaka'));

    $user = User::factory()->create();

    $olderEdition = Edition::query()->create([
        'edition_date' => '2026-02-19',
        'status' => Edition::STATUS_PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
    ]);

    $todayEdition = Edition::query()->create([
        'edition_date' => '2026-02-20',
        'name' => 'City Final',
        'status' => Edition::STATUS_PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
    ]);

    Page::query()->create([
        'edition_id' => $olderEdition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-19/original/page-0001.jpg',
        'image_large_path' => 'epaper/2026-02-19/large/page-0001.jpg',
        'image_thumb_path' => 'epaper/2026-02-19/thumb/page-0001.jpg',
        'width' => 2100,
        'height' => 2800,
        'uploaded_by' => $user->id,
    ]);

    Page::query()->create([
        'edition_id' => $todayEdition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/original/page-0001.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page-0001.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0001.jpg',
        'width' => 2100,
        'height' => 2800,
        'uploaded_by' => $user->id,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Epaper/Index')
            ->where('edition.edition_date', '2026-02-20')
            ->where('selected_date', '2026-02-20')
            ->where('selected_page_no', 1)
            ->where('current_page.page_no', 1)
            ->where('selected_edition.id', $todayEdition->id)
            ->where('selected_edition.name', 'City Final')
            ->has('editions_for_date', 1)
            ->where('editions_for_date.0.id', $todayEdition->id)
            ->has('categories', 0)
            ->has('available_dates')
        );
});

test('homepage falls back to latest published edition when today is missing', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 2, 25, 9, 0, 0, 'Asia/Dhaka'));

    $user = User::factory()->create();

    $olderEdition = Edition::query()->create([
        'edition_date' => '2026-02-22',
        'status' => Edition::STATUS_PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
    ]);

    $latestEdition = Edition::query()->create([
        'edition_date' => '2026-02-24',
        'name' => 'Late City',
        'status' => Edition::STATUS_PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
    ]);

    Page::query()->create([
        'edition_id' => $olderEdition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-22/original/page-0001.jpg',
        'image_large_path' => 'epaper/2026-02-22/large/page-0001.jpg',
        'image_thumb_path' => 'epaper/2026-02-22/thumb/page-0001.jpg',
        'width' => 2100,
        'height' => 2800,
        'uploaded_by' => $user->id,
    ]);

    Page::query()->create([
        'edition_id' => $latestEdition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-24/original/page-0001.jpg',
        'image_large_path' => 'epaper/2026-02-24/large/page-0001.jpg',
        'image_thumb_path' => 'epaper/2026-02-24/thumb/page-0001.jpg',
        'width' => 2100,
        'height' => 2800,
        'uploaded_by' => $user->id,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Epaper/Index')
            ->where('edition.edition_date', '2026-02-24')
            ->where('selected_date', '2026-02-24')
            ->where('selected_page_no', 1)
            ->where('selected_edition.id', $latestEdition->id)
            ->where('selected_edition.name', 'Late City')
            ->has('editions_for_date', 1)
        );
});

test('viewer returns pages categories hotspots and date navigation payload', function () {
    $user = User::factory()->create();

    $edition = Edition::query()->create([
        'edition_date' => '2026-02-20',
        'status' => Edition::STATUS_PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
    ]);

    $category = Category::query()->create([
        'name' => 'প্রথম পাতা',
        'position' => 1,
        'is_active' => true,
    ]);

    $categoryWithoutPage = Category::query()->create([
        'name' => 'খেলা',
        'position' => 2,
        'is_active' => true,
    ]);

    $firstPage = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 1,
        'category_id' => $category->id,
        'image_original_path' => 'epaper/2026-02-20/original/page-0001.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page-0001.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0001.jpg',
        'width' => 2100,
        'height' => 2800,
        'uploaded_by' => $user->id,
    ]);

    Page::query()->create([
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

    PageHotspot::query()->create([
        'page_id' => $firstPage->id,
        'type' => 'relation',
        'relation_kind' => 'next',
        'target_page_no' => 2,
        'x' => 0.100001,
        'y' => 0.200001,
        'w' => 0.300001,
        'h' => 0.400001,
        'label' => 'Go next',
        'created_by' => $user->id,
    ]);

    $this->get(route('epaper.viewer', ['date' => '2026-02-20', 'pageNo' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Epaper/Viewer')
            ->where('edition_date', '2026-02-20')
            ->where('current_page_no', 1)
            ->has('pages', 2)
            ->where('pages.0.page_no', 1)
            ->where('pages.0.category_id', $category->id)
            ->where('pages.0.category_name', $category->name)
            ->has('categories', 2)
            ->where('categories.0.id', $category->id)
            ->where('categories.0.name', $category->name)
            ->where('categories.1.id', $categoryWithoutPage->id)
            ->where('categories.1.name', $categoryWithoutPage->name)
            ->where('selected_edition.id', $edition->id)
            ->has('editions_for_date', 1)
            ->where('editions_for_date.0.id', $edition->id)
            ->has('page.hotspots', 1)
            ->where('page.hotspots.0.relation_kind', 'next')
            ->where('page.hotspots.0.target_page_no', 2)
            ->where('page.hotspots.0.label', 'Go next')
            ->has('available_dates')
            ->where('adsBySlot.1', [])
            ->where('adsBySlot.8', [])
        );
});
