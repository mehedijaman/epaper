<?php

use App\Models\Edition;
use App\Models\Page;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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

test('operator can access editions workflow routes', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)
        ->get(route('epadmin.dashboard'))
        ->assertOk();

    $this->actingAs($operator)
        ->get(route('epadmin.editions.manage'))
        ->assertOk();

    $this->actingAs($operator)
        ->get(route('epadmin.editions.publish.index'))
        ->assertOk();

    $this->actingAs($operator)
        ->get(route('epadmin.hotspots.index'))
        ->assertOk();
});

test('operator cannot access admin-only epadmin routes', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)
        ->get(route('epadmin.categories.index'))
        ->assertForbidden();

    $this->actingAs($operator)
        ->get(route('epadmin.ads.index'))
        ->assertForbidden();

    $this->actingAs($operator)
        ->get(route('epadmin.settings.index'))
        ->assertForbidden();
});

test('public epaper routes only expose published editions', function () {
    $user = User::factory()->create();

    $draftEdition = Edition::query()->create([
        'edition_date' => '2026-02-18',
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $user->id,
    ]);

    $publishedEdition = Edition::query()->create([
        'edition_date' => '2026-02-19',
        'status' => Edition::STATUS_PUBLISHED,
        'published_at' => now(),
        'created_by' => $user->id,
    ]);

    Page::query()->create([
        'edition_id' => $draftEdition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/draft.jpg',
        'image_large_path' => 'epaper/draft-large.jpg',
        'image_thumb_path' => 'epaper/draft-thumb.jpg',
        'width' => 2000,
        'height' => 2500,
        'uploaded_by' => $user->id,
    ]);

    Page::query()->create([
        'edition_id' => $publishedEdition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/published.jpg',
        'image_large_path' => 'epaper/published-large.jpg',
        'image_thumb_path' => 'epaper/published-thumb.jpg',
        'width' => 2000,
        'height' => 2500,
        'uploaded_by' => $user->id,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('2026-02-19')
        ->assertDontSee('2026-02-18');

    $this->get(route('epaper.edition', ['date' => '2026-02-18']))
        ->assertNotFound();

    $this->get(route('epaper.edition', ['date' => '2026-02-19']))
        ->assertOk();
});

test('edition date search reuses existing edition row for manage and publish routes', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-02-20')->startOfDay(),
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->get(route('epadmin.editions.manage', ['date' => '2026-02-20']))
        ->assertOk();

    $this->actingAs($operator)
        ->get(route('epadmin.editions.publish.index', ['date' => '2026-02-20']))
        ->assertOk();

    $this->assertDatabaseCount('editions', 1);
});

test('edition manager can load edition using edition_id query', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-02-20')->startOfDay(),
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $selectedEdition = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-02-21')->startOfDay(),
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->get(route('epadmin.editions.manage', ['edition_id' => $selectedEdition->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedEdition.id', $selectedEdition->id)
            ->where('selectedDate', '2026-02-21')
        );
});

test('searching a missing edition date does not auto-create in manage or publish', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)
        ->get(route('epadmin.editions.manage', ['date' => '2026-03-01']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedEdition', null)
            ->where('dateNotice', 'No editions found for 2026-03-01. Create a new edition to start.')
        );

    $this->actingAs($operator)
        ->get(route('epadmin.editions.publish.index', ['date' => '2026-03-01']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('editions_for_date', [])
            ->where('date_notice', 'No editions found for 2026-03-01. Create one from Manage Pages first.')
        );

    $this->assertDatabaseCount('editions', 0);
});

test('manage page can create a draft edition explicitly', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $response = $this->actingAs($operator)
        ->post(route('epadmin.editions.store'), [
            'edition_date' => '2026-03-02',
            'name' => 'Morning',
        ]);

    $createdEdition = Edition::query()
        ->forDate('2026-03-02')
        ->latest('id')
        ->firstOrFail();

    $response->assertRedirect(route('epadmin.editions.manage', [
        'date' => '2026-03-02',
        'edition_id' => $createdEdition->id,
    ]));

    expect(
        Edition::query()
            ->forDate('2026-03-02')
            ->where('name', 'Morning')
            ->where('status', Edition::STATUS_DRAFT)
            ->exists()
    )->toBeTrue();
});

test('manage page can update and clear edition name', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $edition = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-03-07')->startOfDay(),
        'name' => null,
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->patch(route('epadmin.editions.update', ['edition' => $edition->id]), [
            'name' => 'City Late Edition',
        ])
        ->assertRedirect(route('epadmin.editions.manage', [
            'date' => '2026-03-07',
            'edition_id' => $edition->id,
        ]));

    $this->assertDatabaseHas('editions', [
        'id' => $edition->id,
        'name' => 'City Late Edition',
    ]);

    $this->actingAs($operator)
        ->patch(route('epadmin.editions.update', ['edition' => $edition->id]), [
            'name' => '   ',
        ])
        ->assertRedirect(route('epadmin.editions.manage', [
            'date' => '2026-03-07',
            'edition_id' => $edition->id,
        ]));

    $this->assertDatabaseHas('editions', [
        'id' => $edition->id,
        'name' => null,
    ]);
});

test('operator cannot delete an edition', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $edition = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-03-08')->startOfDay(),
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->delete(route('epadmin.editions.destroy', ['edition' => $edition->id]))
        ->assertForbidden();

    $this->assertDatabaseHas('editions', ['id' => $edition->id]);
});

test('admin can delete an edition and is redirected to remaining edition for that date', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $remainingEdition = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-03-09')->startOfDay(),
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $admin->id,
    ]);

    $editionToDelete = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-03-09')->startOfDay(),
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('epadmin.editions.destroy', ['edition' => $editionToDelete->id]))
        ->assertRedirect(route('epadmin.editions.manage', [
            'date' => '2026-03-09',
            'edition_id' => $remainingEdition->id,
        ]));

    $this->assertDatabaseMissing('editions', ['id' => $editionToDelete->id]);
    $this->assertDatabaseHas('editions', ['id' => $remainingEdition->id]);
});

test('manage page lists editions for selected date ordered by newest first', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $older = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-03-06')->startOfDay(),
        'name' => 'Morning',
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $newer = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-03-06')->startOfDay(),
        'name' => 'Evening',
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->get(route('epadmin.editions.manage', ['date' => '2026-03-06']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedDate', '2026-03-06')
            ->where('selectedEdition', null)
            ->has('editionsForDate', 2)
            ->where('editionsForDate.0.id', $newer->id)
            ->where('editionsForDate.1.id', $older->id)
        );
});

test('publish page includes readiness blocker when edition has no pages', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $edition = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-03-03')->startOfDay(),
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->get(route('epadmin.editions.publish.index', ['date' => '2026-03-03']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected_edition_id', null)
            ->has('editions_for_date', 1)
            ->where('editions_for_date.0.id', $edition->id)
            ->where('editions_for_date.0.publish_readiness.is_ready', false)
            ->where('editions_for_date.0.publish_readiness.blockers', ['Add at least one page before publishing.'])
        );
});

test('publish page lists all editions for selected date ordered by newest first', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $older = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-03-10')->startOfDay(),
        'name' => 'Morning',
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    $newer = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-03-10')->startOfDay(),
        'name' => 'Evening',
        'status' => Edition::STATUS_PUBLISHED,
        'published_at' => now(),
        'created_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->get(route('epadmin.editions.publish.index', ['date' => '2026-03-10']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('date', '2026-03-10')
            ->where('selected_edition_id', null)
            ->has('editions_for_date', 2)
            ->where('editions_for_date.0.id', $newer->id)
            ->where('editions_for_date.1.id', $older->id)
        );
});

test('edition cannot be published when numbering has gaps', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $edition = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-03-04')->startOfDay(),
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-03-04/original/page-0001.jpg',
        'image_large_path' => 'epaper/2026-03-04/large/page-0001.jpg',
        'image_thumb_path' => 'epaper/2026-03-04/thumb/page-0001.jpg',
        'width' => 2000,
        'height' => 2500,
        'uploaded_by' => $operator->id,
    ]);

    Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 3,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-03-04/original/page-0003.jpg',
        'image_large_path' => 'epaper/2026-03-04/large/page-0003.jpg',
        'image_thumb_path' => 'epaper/2026-03-04/thumb/page-0003.jpg',
        'width' => 2000,
        'height' => 2500,
        'uploaded_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->from(route('epadmin.editions.publish.index', ['date' => '2026-03-04']))
        ->post(route('epadmin.editions.publish'), [
            'edition_id' => $edition->id,
        ])
        ->assertRedirect(route('epadmin.editions.publish.index', ['date' => '2026-03-04']))
        ->assertSessionHasErrors([
            'edition_id' => 'Page numbering has a gap: missing page 2.',
        ]);

    $edition->refresh();

    expect($edition->status)->toBe(Edition::STATUS_DRAFT)
        ->and($edition->published_at)->toBeNull();
});

test('edition can be published when readiness checks pass', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $edition = Edition::query()->create([
        'edition_date' => CarbonImmutable::parse('2026-03-05')->startOfDay(),
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $operator->id,
    ]);

    Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 1,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-03-05/original/page-0001.jpg',
        'image_large_path' => 'epaper/2026-03-05/large/page-0001.jpg',
        'image_thumb_path' => 'epaper/2026-03-05/thumb/page-0001.jpg',
        'width' => 2000,
        'height' => 2500,
        'uploaded_by' => $operator->id,
    ]);

    Page::query()->create([
        'edition_id' => $edition->id,
        'page_no' => 2,
        'category_id' => null,
        'image_original_path' => 'epaper/2026-03-05/original/page-0002.jpg',
        'image_large_path' => 'epaper/2026-03-05/large/page-0002.jpg',
        'image_thumb_path' => 'epaper/2026-03-05/thumb/page-0002.jpg',
        'width' => 2000,
        'height' => 2500,
        'uploaded_by' => $operator->id,
    ]);

    $this->actingAs($operator)
        ->post(route('epadmin.editions.publish'), [
            'edition_id' => $edition->id,
        ])
        ->assertRedirect(route('epadmin.editions.publish.index', [
            'date' => '2026-03-05',
            'edition_id' => $edition->id,
        ]));

    $edition->refresh();

    expect($edition->status)->toBe(Edition::STATUS_PUBLISHED)
        ->and($edition->published_at)->not->toBeNull();
});
