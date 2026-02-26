<?php

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    $adminRole->syncPermissions($permissions);
    $this->admin = \App\Models\User::factory()->create();
    $this->admin->assignRole('admin');
});

test('category store auto-assigns the next sequential position', function () {
    Category::query()->create([
        'name' => 'Top',
        'position' => 1,
        'is_active' => true,
    ]);
    Category::query()->create([
        'name' => 'Skipped',
        'position' => 3,
        'is_active' => false,
    ]);

    $this->actingAs($this->admin)
        ->post(route('epadmin.categories.store'), [
            'name' => 'Latest',
            'is_active' => true,
        ])
        ->assertRedirect(route('epadmin.categories.index'));

    $ordered = Category::query()
        ->orderBy('position')
        ->get(['name', 'position']);

    expect($ordered->pluck('position')->all())->toBe([1, 2, 3]);
    expect(Category::query()->where('name', 'Latest')->value('position'))->toBe(3);
});

test('category update accepts name and is_active without position input', function () {
    $category = Category::query()->create([
        'name' => 'Original',
        'position' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->put(route('epadmin.categories.update', ['category' => $category->id]), [
            'name' => 'Updated',
            'is_active' => false,
        ])
        ->assertRedirect(route('epadmin.categories.index'));

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'name' => 'Updated',
        'position' => 1,
        'is_active' => false,
    ]);
});

test('category reorder persists sequential positions from ordered ids', function () {
    $first = Category::query()->create([
        'name' => 'First',
        'position' => 1,
        'is_active' => true,
    ]);
    $second = Category::query()->create([
        'name' => 'Second',
        'position' => 2,
        'is_active' => true,
    ]);
    $third = Category::query()->create([
        'name' => 'Third',
        'position' => 3,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('epadmin.categories.reorder'), [
            'ordered_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertRedirect(route('epadmin.categories.index'));

    $this->assertDatabaseHas('categories', [
        'id' => $third->id,
        'position' => 1,
    ]);
    $this->assertDatabaseHas('categories', [
        'id' => $first->id,
        'position' => 2,
    ]);
    $this->assertDatabaseHas('categories', [
        'id' => $second->id,
        'position' => 3,
    ]);
});

test('category reorder accepts id-position payload for persistence', function () {
    $first = Category::query()->create([
        'name' => 'First',
        'position' => 1,
        'is_active' => true,
    ]);
    $second = Category::query()->create([
        'name' => 'Second',
        'position' => 2,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('epadmin.categories.reorder'), [
            'positions' => [
                ['id' => $first->id, 'position' => 2],
                ['id' => $second->id, 'position' => 1],
            ],
        ])
        ->assertRedirect(route('epadmin.categories.index'));

    $this->assertDatabaseHas('categories', [
        'id' => $first->id,
        'position' => 2,
    ]);
    $this->assertDatabaseHas('categories', [
        'id' => $second->id,
        'position' => 1,
    ]);
});

test('category toggle endpoint updates status', function () {
    $category = Category::query()->create([
        'name' => 'Toggle me',
        'position' => 1,
        'is_active' => false,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('epadmin.categories.toggle', ['category' => $category->id]), [
            'is_active' => true,
        ])
        ->assertRedirect(route('epadmin.categories.index'));

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'is_active' => true,
    ]);
});

test('category delete resequences remaining positions', function () {
    $first = Category::query()->create([
        'name' => 'First',
        'position' => 1,
        'is_active' => true,
    ]);
    $middle = Category::query()->create([
        'name' => 'Middle',
        'position' => 2,
        'is_active' => true,
    ]);
    $last = Category::query()->create([
        'name' => 'Last',
        'position' => 3,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('epadmin.categories.destroy', ['category' => $middle->id]))
        ->assertRedirect(route('epadmin.categories.index'));

    $this->assertDatabaseMissing('categories', [
        'id' => $middle->id,
    ]);
    $this->assertDatabaseHas('categories', [
        'id' => $first->id,
        'position' => 1,
    ]);
    $this->assertDatabaseHas('categories', [
        'id' => $last->id,
        'position' => 2,
    ]);
});
