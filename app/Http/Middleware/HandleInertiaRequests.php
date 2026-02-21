<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $roles = $user?->getRoleNames()->values()->all() ?? [];
        $permissions = $user?->getAllPermissions()->pluck('name')->values()->all() ?? [];

        $abilities = [
            'categories_manage' => $user?->can('categories.manage') ?? false,
            'ads_manage' => $user?->can('ads.manage') ?? false,
            'settings_manage' => $user?->can('settings.manage') ?? false,
            'editions_manage' => $user?->can('editions.manage') ?? false,
            'users_manage' => $user?->can('users.manage') ?? false,
        ];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => $roles,
                'permissions' => $permissions,
                'abilities' => $abilities,
                'is_admin' => in_array('admin', $roles, true),
                'is_operator' => in_array('operator', $roles, true),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warnings' => fn () => $request->session()->get('warnings', []),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
