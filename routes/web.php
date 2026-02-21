<?php

use App\Http\Controllers\EpAdmin\AdController;
use App\Http\Controllers\EpAdmin\CategoryController;
use App\Http\Controllers\EpAdmin\DashboardController;
use App\Http\Controllers\EpAdmin\EditionManageController;
use App\Http\Controllers\EpAdmin\EditionPublishController;
use App\Http\Controllers\EpAdmin\PageController;
use App\Http\Controllers\EpAdmin\PageHotspotController;
use App\Http\Controllers\EpAdmin\SiteSettingController;
use App\Http\Controllers\PublicEpaperController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/welcome', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('welcome');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

Route::get('/', [PublicEpaperController::class, 'index'])->name('home');
Route::redirect('/epaper', '/');

Route::get('/epaper/{date}', [PublicEpaperController::class, 'edition'])
    ->where('date', '\\d{4}-\\d{2}-\\d{2}')
    ->name('epaper.edition');
Route::get('/epaper/{date}/page/{pageNo}', [PublicEpaperController::class, 'viewer'])
    ->where('date', '\\d{4}-\\d{2}-\\d{2}')
    ->whereNumber('pageNo')
    ->name('epaper.viewer');

Route::middleware(['auth'])->prefix('admin')->name('epadmin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('can:categories.manage')->prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::get('/create', [CategoryController::class, 'create'])->name('create');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [CategoryController::class, 'edit'])->name('edit');
        Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
    });

    Route::middleware('can:editions.manage')->group(function () {
        Route::get('/editions/manage', [EditionManageController::class, 'index'])->name('editions.manage');

        Route::get('/editions/publish', [EditionPublishController::class, 'index'])->name('editions.publish.index');
        Route::post('/editions/publish', [EditionPublishController::class, 'publish'])->name('editions.publish');
        Route::post('/editions/unpublish', [EditionPublishController::class, 'unpublish'])->name('editions.unpublish');

        Route::post('/pages/upload', [PageController::class, 'upload'])->name('pages.upload');
        Route::post('/pages/reorder', [PageController::class, 'reorder'])->name('pages.reorder');
        Route::put('/pages/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::put('/pages/{page}/replace', [PageController::class, 'replace'])->name('pages.replace');
        Route::delete('/pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');

        Route::get('/hotspots', [PageHotspotController::class, 'index'])->name('hotspots.index');
        Route::post('/hotspots', [PageHotspotController::class, 'store'])->name('hotspots.store');
        Route::put('/hotspots/{hotspot}', [PageHotspotController::class, 'update'])->name('hotspots.update');
        Route::delete('/hotspots/{hotspot}', [PageHotspotController::class, 'destroy'])->name('hotspots.destroy');
    });

    Route::middleware('can:ads.manage')->group(function () {
        Route::get('/ads', [AdController::class, 'index'])->name('ads.index');
        Route::put('/ads/{slotNo}', [AdController::class, 'update'])
            ->whereNumber('slotNo')
            ->name('ads.update');
    });

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/settings', [SiteSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SiteSettingController::class, 'update'])->name('settings.update');
    });
});

require __DIR__.'/settings.php';
