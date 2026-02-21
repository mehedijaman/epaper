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
            ->where('selected_edition_id', $selectedEdition->id)
            ->where('edition.id', $selectedEdition->id)
            ->where('date', '2026-02-21')
        );
});

test('searching a missing edition date does not auto-create in manage or publish', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)
        ->get(route('epadmin.editions.manage', ['date' => '2026-03-01']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('edition', null)
            ->where('date_notice', 'No edition found for 2026-03-01. Click "Create draft edition" to start.')
        );

    $this->actingAs($operator)
        ->get(route('epadmin.editions.publish.index', ['date' => '2026-03-01']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('edition', null)
            ->where('date_notice', 'No edition found for 2026-03-01. Create it from Manage Pages first.')
        );

    $this->assertDatabaseCount('editions', 0);
});

test('manage date create flag creates draft edition explicitly', function () {
    $operator = User::factory()->create();
    $operator->assignRole('operator');

    $this->actingAs($operator)
        ->get(route('epadmin.editions.manage', ['date' => '2026-03-02', 'create' => 1]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('edition.edition_date', '2026-03-02')
            ->where('edition.status', Edition::STATUS_DRAFT)
            ->where('date_notice', null)
        );

    expect(
        Edition::query()
            ->forDate('2026-03-02')
            ->where('status', Edition::STATUS_DRAFT)
            ->exists()
    )->toBeTrue();
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
            ->where('edition.id', $edition->id)
            ->where('publish_readiness.is_ready', false)
            ->where('publish_readiness.blockers', ['Add at least one page before publishing.'])
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
        ->assertRedirect(route('epadmin.editions.publish.index', ['date' => '2026-03-05']));

    $edition->refresh();

    expect($edition->status)->toBe(Edition::STATUS_PUBLISHED)
        ->and($edition->published_at)->not->toBeNull();
});
