<?php

use App\Models\Edition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

test('admin can create user with roles and direct permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('epadmin.users.accounts.store'), [
        'name' => 'ACL Managed User',
        'email' => 'acl-user@example.com',
        'password' => 'strong-password',
        'password_confirmation' => 'strong-password',
        'roles' => ['operator'],
        'permissions' => ['ads.manage'],
    ]);

    $response->assertRedirect(route('epadmin.users.index'));

    $managedUser = User::query()->where('email', 'acl-user@example.com')->first();

    expect($managedUser)->not()->toBeNull();
    expect($managedUser?->hasRole('operator'))->toBeTrue();
    expect($managedUser?->hasDirectPermission('ads.manage'))->toBeTrue();
    expect(Hash::check('strong-password', (string) $managedUser?->password))->toBeTrue();
});

test('admin can update user profile and keep password unchanged when left blank', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $managedUser = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old-email@example.com',
    ]);
    $managedUser->assignRole('operator');
    $oldPasswordHash = $managedUser->password;

    $response = $this->actingAs($admin)->put(route('epadmin.users.accounts.update', ['user' => $managedUser->id]), [
        'name' => 'New Name',
        'email' => 'new-email@example.com',
        'password' => '',
        'password_confirmation' => '',
        'roles' => ['operator'],
        'permissions' => ['settings.manage'],
    ]);

    $response->assertRedirect(route('epadmin.users.index'));

    $managedUser->refresh();

    expect($managedUser->name)->toBe('New Name');
    expect($managedUser->email)->toBe('new-email@example.com');
    expect($managedUser->password)->toBe($oldPasswordHash);
    expect($managedUser->hasRole('operator'))->toBeTrue();
    expect($managedUser->hasDirectPermission('settings.manage'))->toBeTrue();
});

test('cannot delete own account from acl manager', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->delete(route('epadmin.users.accounts.destroy', ['user' => $admin->id]))
        ->assertSessionHasErrors('user');

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
    ]);
});

test('cannot delete last admin user', function () {
    $manager = User::factory()->create();
    $manager->givePermissionTo('users.manage');

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($manager)
        ->delete(route('epadmin.users.accounts.destroy', ['user' => $admin->id]))
        ->assertSessionHasErrors('user');

    $this->assertDatabaseHas('users', [
        'id' => $admin->id,
    ]);
});

test('cannot delete user who owns epaper content', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $owner = User::factory()->create();
    $owner->assignRole('operator');

    Edition::query()->create([
        'edition_date' => '2026-02-20',
        'status' => Edition::STATUS_DRAFT,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('epadmin.users.accounts.destroy', ['user' => $owner->id]))
        ->assertSessionHasErrors('user');

    $this->assertDatabaseHas('users', [
        'id' => $owner->id,
    ]);
});

test('admin can delete regular user without owned content', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $target = User::factory()->create();
    $target->assignRole('operator');

    $this->actingAs($admin)
        ->delete(route('epadmin.users.accounts.destroy', ['user' => $target->id]))
        ->assertRedirect(route('epadmin.users.index'));

    $this->assertDatabaseMissing('users', [
        'id' => $target->id,
    ]);
});
