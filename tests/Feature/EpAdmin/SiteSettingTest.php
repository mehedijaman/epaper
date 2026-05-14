<?php

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('settings.manage', 'web');
    $adminRole = Role::findOrCreate('admin', 'web');
    $adminRole->syncPermissions(['settings.manage']);
});

function adminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

test('settings index includes favicon_url and site_name', function (): void {
    $this->actingAs(adminUser())
        ->get(route('epadmin.settings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('EpAdmin/Settings/Index')
            ->has('favicon_url')
            ->has('settings', fn (Assert $settings) => $settings
                ->has(SiteSetting::FAVICON_PATH)
                ->has(SiteSetting::SITE_NAME)
                ->has(SiteSetting::LOGO_PATH)
                ->has(SiteSetting::FOOTER_EDITOR_INFO)
                ->has(SiteSetting::FOOTER_CONTACT_INFO)
                ->has(SiteSetting::FOOTER_COPYRIGHT)
            )
        );
});

test('can save site name', function (): void {
    $this->actingAs(adminUser())
        ->put(route('epadmin.settings.update'), [
            'site_name' => 'The Daily Paper',
        ])
        ->assertRedirect(route('epadmin.settings.index'));

    expect(SiteSetting::getValue(SiteSetting::SITE_NAME))->toBe('The Daily Paper');
});

test('can upload favicon', function (): void {
    Storage::fake(config('epaper.disk'));

    $favicon = UploadedFile::fake()->image('favicon.png', 32, 32);

    $this->actingAs(adminUser())
        ->put(route('epadmin.settings.update'), [
            'favicon' => $favicon,
        ])
        ->assertRedirect(route('epadmin.settings.index'));

    $storedPath = SiteSetting::getValue(SiteSetting::FAVICON_PATH);

    expect($storedPath)->not->toBeNull();
    Storage::disk(config('epaper.disk'))->assertExists($storedPath);
});

test('can remove favicon', function (): void {
    Storage::fake(config('epaper.disk'));

    $favicon = UploadedFile::fake()->image('favicon.png', 32, 32);
    $storedPath = ltrim($favicon->store('epaper/settings/favicon', config('epaper.disk')), '/');
    SiteSetting::setValue(SiteSetting::FAVICON_PATH, $storedPath);

    $this->actingAs(adminUser())
        ->put(route('epadmin.settings.update'), [
            'remove_favicon' => true,
        ])
        ->assertRedirect(route('epadmin.settings.index'));

    expect(SiteSetting::getValue(SiteSetting::FAVICON_PATH))->toBeNull();
    Storage::disk(config('epaper.disk'))->assertMissing($storedPath);
});

test('favicon validation rejects oversized files', function (): void {
    Storage::fake(config('epaper.disk'));

    $oversized = UploadedFile::fake()->image('favicon.png')->size(3000);

    $this->actingAs(adminUser())
        ->put(route('epadmin.settings.update'), [
            'favicon' => $oversized,
        ])
        ->assertSessionHasErrors('favicon');
});
