<?php

use App\Models\Edition;
use App\Models\Page;
use App\Models\PageHotspot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $permissions = [
        'categories.manage',
        'users.manage',
        'ads.manage',
        'settings.manage',
        'editions.manage',
    ];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $adminRole = Role::findOrCreate('admin', 'web');
    $operatorRole = Role::findOrCreate('operator', 'web');

    $adminRole->syncPermissions($permissions);
    $operatorRole->syncPermissions(['editions.manage']);
});

test('page manager can update page metadata and keep hotspot targets in sync', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $edition = Edition::query()->create([
        'edition_date' => '2026-02-20',
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $pageOne = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/original/page-0001-old.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page-0001-old.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0001-old.jpg',
        'width' => 2000,
        'height' => 2600,
        'uploaded_by' => $operator->id,
    ]);

    $pageTwo = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 2,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/original/page-0002-old.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page-0002-old.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0002-old.jpg',
        'width' => 2000,
        'height' => 2600,
        'uploaded_by' => $operator->id,
    ]);

    $hotspot = PageHotspot::query()->create([
        'page_id' => $pageTwo->id,
        'type' => 'relation',
        'relation_kind' => 'next',
        'target_page_no' => 1,
        'x' => 0.1,
        'y' => 0.2,
        'w' => 0.3,
        'h' => 0.4,
        'label' => 'Jump to page 1',
        'created_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->put(route('epadmin.pages.update', ['page' => $pageOne->id]), [
            'page_no' => 5,
            'category_id' => null,
        ])
        ->assertRedirect(route('epadmin.editions.manage', ['date' => '2026-02-20']));

    $this->assertDatabaseHas('pages', [
        'id' => $pageOne->id,
        'page_no' => 5,
    ]);

    $this->assertDatabaseHas('page_hotspots', [
        'id' => $hotspot->id,
        'target_page_no' => 5,
    ]);
});

test('page manager can reorder pages and remap hotspot target page numbers', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $edition = Edition::query()->create([
        'edition_date' => '2026-02-20',
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $pageOne = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/original/page-0001.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page-0001.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0001.jpg',
        'width' => 2000,
        'height' => 2600,
        'uploaded_by' => $operator->id,
    ]);

    $pageTwo = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 2,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/original/page-0002.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page-0002.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0002.jpg',
        'width' => 2000,
        'height' => 2600,
        'uploaded_by' => $operator->id,
    ]);

    $pageThree = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 3,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/original/page-0003.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page-0003.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0003.jpg',
        'width' => 2000,
        'height' => 2600,
        'uploaded_by' => $operator->id,
    ]);

    $hotspotToPageOne = PageHotspot::query()->create([
        'page_id' => $pageThree->id,
        'type' => 'relation',
        'relation_kind' => 'next',
        'target_page_no' => 1,
        'x' => 0.1,
        'y' => 0.2,
        'w' => 0.3,
        'h' => 0.4,
        'label' => 'To old page 1',
        'created_by' => $operator->id,
    ]);

    $hotspotToPageTwo = PageHotspot::query()->create([
        'page_id' => $pageOne->id,
        'type' => 'relation',
        'relation_kind' => 'next',
        'target_page_no' => 2,
        'x' => 0.2,
        'y' => 0.3,
        'w' => 0.2,
        'h' => 0.2,
        'label' => 'To old page 2',
        'created_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->post(route('epadmin.pages.reorder'), [
            'edition_id' => $edition->id,
            'ordered_page_ids' => [$pageThree->id, $pageOne->id, $pageTwo->id],
        ])
        ->assertRedirect(route('epadmin.editions.manage', ['date' => '2026-02-20']));

    $this->assertDatabaseHas('pages', [
        'id' => $pageThree->id,
        'page_no' => 1,
    ]);
    $this->assertDatabaseHas('pages', [
        'id' => $pageOne->id,
        'page_no' => 2,
    ]);
    $this->assertDatabaseHas('pages', [
        'id' => $pageTwo->id,
        'page_no' => 3,
    ]);

    $this->assertDatabaseHas('page_hotspots', [
        'id' => $hotspotToPageOne->id,
        'target_page_no' => 2,
    ]);
    $this->assertDatabaseHas('page_hotspots', [
        'id' => $hotspotToPageTwo->id,
        'target_page_no' => 3,
    ]);
});

test('page manager can replace a page image', function () {
    Storage::fake('public');

    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $edition = Edition::query()->create([
        'edition_date' => '2026-02-20',
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $page = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/original/page-0001-old.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page-0001-old.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0001-old.jpg',
        'width' => 1900,
        'height' => 2470,
        'uploaded_by' => $operator->id,
    ]);

    Storage::disk('public')->put($page->image_original_path, 'old-original');
    Storage::disk('public')->put($page->image_large_path, 'old-large');
    Storage::disk('public')->put($page->image_thumb_path, 'old-thumb');

    $this->actingAs($operator)
        ->put(route('epadmin.pages.replace', ['page' => $page->id]), [
            'file' => UploadedFile::fake()->image('page-0001.jpg', 1200, 1600),
        ])
        ->assertRedirect(route('epadmin.editions.manage', ['date' => '2026-02-20']));

    $page->refresh();

    expect($page->image_original_path)->not()->toBe('epaper/2026-02-20/original/page-0001-old.jpg');
    expect($page->image_large_path)->not()->toBe('epaper/2026-02-20/large/page-0001-old.jpg');
    expect($page->image_thumb_path)->not()->toBe('epaper/2026-02-20/thumb/page-0001-old.jpg');

    Storage::disk('public')->assertMissing('epaper/2026-02-20/original/page-0001-old.jpg');
    Storage::disk('public')->assertMissing('epaper/2026-02-20/large/page-0001-old.jpg');
    Storage::disk('public')->assertMissing('epaper/2026-02-20/thumb/page-0001-old.jpg');

    Storage::disk('public')->assertExists((string) $page->image_original_path);
    Storage::disk('public')->assertExists((string) $page->image_large_path);
    Storage::disk('public')->assertExists((string) $page->image_thumb_path);
});

test('page manager can delete a page and its hotspot mappings', function () {
    Storage::fake('public');

    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $edition = Edition::query()->create([
        'edition_date' => '2026-02-20',
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $page = Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-02-20/original/page-0001-old.jpg',
        'image_large_path' => 'epaper/2026-02-20/large/page-0001-old.jpg',
        'image_thumb_path' => 'epaper/2026-02-20/thumb/page-0001-old.jpg',
        'width' => 1900,
        'height' => 2470,
        'uploaded_by' => $operator->id,
    ]);

    $hotspot = PageHotspot::query()->create([
        'page_id' => $page->id,
        'type' => 'relation',
        'relation_kind' => 'next',
        'target_page_no' => 1,
        'x' => 0.1,
        'y' => 0.2,
        'w' => 0.3,
        'h' => 0.4,
        'label' => null,
        'created_by' => $operator->id,
    ]);

    Storage::disk('public')->put($page->image_original_path, 'old-original');
    Storage::disk('public')->put($page->image_large_path, 'old-large');
    Storage::disk('public')->put($page->image_thumb_path, 'old-thumb');

    $this->actingAs($operator)
        ->delete(route('epadmin.pages.destroy', ['page' => $page->id]))
        ->assertRedirect(route('epadmin.editions.manage', ['date' => '2026-02-20']));

    $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    $this->assertDatabaseMissing('page_hotspots', ['id' => $hotspot->id]);

    Storage::disk('public')->assertMissing('epaper/2026-02-20/original/page-0001-old.jpg');
    Storage::disk('public')->assertMissing('epaper/2026-02-20/large/page-0001-old.jpg');
    Storage::disk('public')->assertMissing('epaper/2026-02-20/thumb/page-0001-old.jpg');
});
