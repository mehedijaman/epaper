<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EpAdmin\UserAclUpdateRequest;
use App\Http\Requests\EpAdmin\UserPermissionStoreRequest;
use App\Http\Requests\EpAdmin\UserPermissionUpdateRequest;
use App\Http\Requests\EpAdmin\UserRoleStoreRequest;
use App\Http\Requests\EpAdmin\UserRoleUpdateRequest;
use App\Http\Requests\EpAdmin\UserStoreRequest;
use App\Http\Requests\EpAdmin\UserUpdateRequest;
use App\Models\Ad;
use App\Models\Edition;
use App\Models\Page;
use App\Models\PageHotspot;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserAclController extends Controller
{
    /**
     * @var list<string>
     */
    private const SYSTEM_PERMISSIONS = [
        'categories.manage',
        'users.manage',
        'ads.manage',
        'settings.manage',
        'editions.manage',
    ];

    public function index(): Response
    {
        /** @var Collection<int, User> $users */
        $users = User::query()
            ->with([
                'roles' => fn ($query) => $query->orderBy('name'),
                'roles.permissions' => fn ($query) => $query->orderBy('name'),
                'permissions' => fn ($query) => $query->orderBy('name'),
            ])
            ->orderBy('name')
            ->orderBy('email')
            ->get();

        /** @var Collection<int, Role> $roles */
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with([
                'permissions' => fn ($query) => $query->orderBy('name'),
            ])
            ->withCount('users')
            ->orderBy('name')
            ->get();

        /** @var Collection<int, Permission> $permissions */
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->withCount(['roles', 'users'])
            ->orderBy('name')
            ->get();

        return Inertia::render('EpAdmin/Users/Index', [
            'users' => $users->map(function (User $user): array {
                $effectivePermissions = $user
                    ->getAllPermissions()
                    ->pluck('name')
                    ->map(fn (mixed $name): string => (string) $name)
                    ->sort()
                    ->values()
                    ->all();

                return [
                    'id' => $user->id,
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                    'email_verified_at' => $user->email_verified_at?->toISOString(),
                    'roles' => $user->roles
                        ->pluck('name')
                        ->map(fn (mixed $name): string => (string) $name)
                        ->values()
                        ->all(),
                    'direct_permissions' => $user->permissions
                        ->pluck('name')
                        ->map(fn (mixed $name): string => (string) $name)
                        ->values()
                        ->all(),
                    'effective_permissions' => $effectivePermissions,
                ];
            })->values()->all(),
            'roles' => $roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => (string) $role->name,
                'label' => $this->toLabel($role->name),
                'permissions' => $role->permissions
                    ->pluck('name')
                    ->map(fn (mixed $name): string => (string) $name)
                    ->values()
                    ->all(),
                'users_count' => (int) ($role->users_count ?? 0),
            ])->values()->all(),
            'permissions' => $permissions->map(fn (Permission $permission): array => [
                'id' => $permission->id,
                'name' => (string) $permission->name,
                'label' => $this->toLabel($permission->name),
                'roles_count' => (int) ($permission->roles_count ?? 0),
                'users_count' => (int) ($permission->users_count ?? 0),
            ])->values()->all(),
        ]);
    }

    public function storeUser(UserStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $roles = collect($validated['roles'] ?? [])
            ->map(fn (mixed $name): string => (string) $name)
            ->unique()
            ->values()
            ->all();
        $permissions = collect($validated['permissions'] ?? [])
            ->map(fn (mixed $name): string => (string) $name)
            ->unique()
            ->values()
            ->all();

        $user = User::query()->create([
            'name' => (string) ($validated['name'] ?? ''),
            'email' => (string) ($validated['email'] ?? ''),
            'password' => (string) ($validated['password'] ?? ''),
        ]);

        $user->syncRoles($roles);
        $user->syncPermissions($permissions);

        return redirect()
            ->route('epadmin.users.index')
            ->with('success', sprintf('User %s created successfully.', $user->name));
    }

    public function storeRole(UserRoleStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $name = (string) ($validated['name'] ?? '');
        $permissions = collect($validated['permissions'] ?? [])
            ->map(fn (mixed $permission): string => (string) $permission)
            ->unique()
            ->values()
            ->all();

        $role = Role::query()->create([
            'name' => $name,
            'guard_name' => 'web',
        ]);
        $role->syncPermissions($permissions);

        return redirect()
            ->route('epadmin.users.index')
            ->with('success', sprintf('Role %s created successfully.', $name));
    }

    public function update(UserAclUpdateRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $roles = collect($validated['roles'] ?? [])
            ->map(fn (mixed $name): string => (string) $name)
            ->unique()
            ->values()
            ->all();
        $permissions = collect($validated['permissions'] ?? [])
            ->map(fn (mixed $name): string => (string) $name)
            ->unique()
            ->values()
            ->all();

        $isRemovingAdmin = $user->hasRole('admin') && ! in_array('admin', $roles, true);

        if ($isRemovingAdmin) {
            $anotherAdminExists = User::query()
                ->whereKeyNot($user->id)
                ->whereHas('roles', fn ($query) => $query->where('name', 'admin')->where('guard_name', 'web'))
                ->exists();

            if (! $anotherAdminExists) {
                throw ValidationException::withMessages([
                    'roles' => 'At least one admin user is required.',
                ]);
            }
        }

        $user->syncRoles($roles);
        $user->syncPermissions($permissions);

        return redirect()
            ->route('epadmin.users.index')
            ->with('success', sprintf('ACL updated for %s.', $user->name));
    }

    public function updateRole(UserRoleUpdateRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->guard_name !== 'web', 404);

        $validated = $request->validated();
        $nextName = (string) ($validated['name'] ?? $role->name);
        $permissions = collect($validated['permissions'] ?? [])
            ->map(fn (mixed $permission): string => (string) $permission)
            ->unique()
            ->values()
            ->all();

        if (in_array($role->name, ['admin', 'operator'], true) && $role->name !== $nextName) {
            throw ValidationException::withMessages([
                'name' => 'System roles cannot be renamed.',
            ]);
        }

        $role->update([
            'name' => $nextName,
        ]);
        $role->syncPermissions($permissions);

        return redirect()
            ->route('epadmin.users.index')
            ->with('success', sprintf('Role %s updated successfully.', $nextName));
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        abort_if($role->guard_name !== 'web', 404);

        if (in_array($role->name, ['admin', 'operator'], true)) {
            throw ValidationException::withMessages([
                'role' => 'System roles cannot be deleted.',
            ]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Role is assigned to one or more users and cannot be deleted.',
            ]);
        }

        $name = $role->name;
        $role->delete();

        return redirect()
            ->route('epadmin.users.index')
            ->with('success', sprintf('Role %s deleted successfully.', $name));
    }

    public function updateUser(UserUpdateRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $roles = collect($validated['roles'] ?? [])
            ->map(fn (mixed $name): string => (string) $name)
            ->unique()
            ->values()
            ->all();
        $permissions = collect($validated['permissions'] ?? [])
            ->map(fn (mixed $name): string => (string) $name)
            ->unique()
            ->values()
            ->all();
        $isRemovingAdmin = $user->hasRole('admin') && ! in_array('admin', $roles, true);

        if ($isRemovingAdmin && ! $this->hasAnotherAdmin($user->id)) {
            throw ValidationException::withMessages([
                'roles' => 'At least one admin user is required.',
            ]);
        }

        $payload = [
            'name' => (string) ($validated['name'] ?? ''),
            'email' => (string) ($validated['email'] ?? ''),
        ];
        $password = (string) ($validated['password'] ?? '');

        if ($password !== '') {
            $payload['password'] = $password;
        }

        $user->update($payload);
        $user->syncRoles($roles);
        $user->syncPermissions($permissions);

        return redirect()
            ->route('epadmin.users.index')
            ->with('success', sprintf('User %s updated successfully.', $user->name));
    }

    public function destroyUser(User $user): RedirectResponse
    {
        $authUserId = auth()->id();

        if ($authUserId !== null && (int) $authUserId === (int) $user->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->hasRole('admin') && ! $this->hasAnotherAdmin($user->id)) {
            throw ValidationException::withMessages([
                'user' => 'Cannot delete the last admin user.',
            ]);
        }

        if ($this->hasOwnedContent($user->id)) {
            throw ValidationException::withMessages([
                'user' => 'This user has created content. Reassign or remove related content first.',
            ]);
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('epadmin.users.index')
            ->with('success', sprintf('User %s deleted successfully.', $name));
    }

    public function storePermission(UserPermissionStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $name = (string) ($validated['name'] ?? '');

        Permission::query()->create([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        return redirect()
            ->route('epadmin.users.index')
            ->with('success', sprintf('Permission %s created successfully.', $name));
    }

    public function updatePermission(UserPermissionUpdateRequest $request, Permission $permission): RedirectResponse
    {
        abort_if($permission->guard_name !== 'web', 404);

        $validated = $request->validated();
        $nextName = (string) ($validated['name'] ?? $permission->name);

        if (in_array($permission->name, self::SYSTEM_PERMISSIONS, true) && $permission->name !== $nextName) {
            throw ValidationException::withMessages([
                'name' => 'System permissions cannot be renamed.',
            ]);
        }

        $permission->update([
            'name' => $nextName,
        ]);

        return redirect()
            ->route('epadmin.users.index')
            ->with('success', sprintf('Permission %s updated successfully.', $nextName));
    }

    public function destroyPermission(Permission $permission): RedirectResponse
    {
        abort_if($permission->guard_name !== 'web', 404);

        if (in_array($permission->name, self::SYSTEM_PERMISSIONS, true)) {
            throw ValidationException::withMessages([
                'permission' => 'System permissions cannot be deleted.',
            ]);
        }

        if ($permission->roles()->exists() || $permission->users()->exists()) {
            throw ValidationException::withMessages([
                'permission' => 'Permission is assigned to one or more roles/users and cannot be deleted.',
            ]);
        }

        $name = $permission->name;
        $permission->delete();

        return redirect()
            ->route('epadmin.users.index')
            ->with('success', sprintf('Permission %s deleted successfully.', $name));
    }

    private function toLabel(string $value): string
    {
        return str($value)
            ->replace(['.', '_', '-'], ' ')
            ->title()
            ->toString();
    }

    private function hasAnotherAdmin(int $excludingUserId): bool
    {
        return User::query()
            ->whereKeyNot($excludingUserId)
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin')->where('guard_name', 'web'))
            ->exists();
    }

    private function hasOwnedContent(int $userId): bool
    {
        return Edition::query()->where('created_by', $userId)->exists()
            || Page::query()->where('uploaded_by', $userId)->exists()
            || PageHotspot::query()->where('created_by', $userId)->exists()
            || Ad::query()->where('created_by', $userId)->exists();
    }
}
