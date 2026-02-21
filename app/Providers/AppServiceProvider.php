<?php

namespace App\Providers;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Edition;
use App\Models\Page;
use App\Models\PageHotspot;
use App\Models\SiteSetting;
use App\Policies\AdPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\EditionPolicy;
use App\Policies\PageHotspotPolicy;
use App\Policies\PagePolicy;
use App\Policies\SiteSettingPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Edition::class, EditionPolicy::class);
        Gate::policy(Page::class, PagePolicy::class);
        Gate::policy(PageHotspot::class, PageHotspotPolicy::class);
        Gate::policy(Ad::class, AdPolicy::class);
        Gate::policy(SiteSetting::class, SiteSettingPolicy::class);
    }
}
