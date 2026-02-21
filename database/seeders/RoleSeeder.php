<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

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

        $firstUser = User::query()->oldest('id')->first();

        if ($firstUser !== null) {
            $firstUser->assignRole($adminRole);

            return;
        }

        $adminUser = User::query()->create([
            'name' => env('EPAPER_ADMIN_NAME', 'ePaper Admin'),
            'email' => env('EPAPER_ADMIN_EMAIL', 'admin@example.com'),
            'password' => env('EPAPER_ADMIN_PASSWORD', 'password'),
            'email_verified_at' => now(),
        ]);

        $adminUser->assignRole($adminRole);
    }
}
