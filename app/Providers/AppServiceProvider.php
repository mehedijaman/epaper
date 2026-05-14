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
use App\Support\DiskUrl;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
        $this->composeSiteSettings();
    }

    /**
     * Share dynamic site settings with the root Blade template.
     */
    protected function composeSiteSettings(): void
    {
        View::composer('app', function (\Illuminate\View\View $view): void {
            $disk = (string) config('epaper.disk');
            $faviconPath = SiteSetting::getValue(SiteSetting::FAVICON_PATH);
            $faviconUrl = $faviconPath !== null && $faviconPath !== ''
                ? DiskUrl::fromPath($disk, $faviconPath)
                : null;
            $siteName = SiteSetting::getValue(SiteSetting::SITE_NAME);

            $view->with('faviconUrl', $faviconUrl);
            $view->with('siteName', $siteName !== null && $siteName !== '' ? $siteName : config('app.name'));
        });
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
