<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Page;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $todayDhaka = CarbonImmutable::now('Asia/Dhaka')->toDateString();

        $todayPagesCount = Page::query()
            ->whereHas('edition', function ($query) use ($todayDhaka): void {
                $query->published()->forDate($todayDhaka);
            })
            ->count();

        return Inertia::render('EpAdmin/Dashboard', [
            'todayPagesCount' => $todayPagesCount,
            'activeAdsCount' => Ad::query()->active()->count(),
            'totalUsersCount' => User::query()->count(),
            'totalCategoriesCount' => Category::query()->count(),
        ]);
    }
}
