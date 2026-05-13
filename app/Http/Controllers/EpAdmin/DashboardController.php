<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Edition;
use App\Models\Page;
use App\Models\PageHotspot;
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

        $totalEditions = Edition::query()->count();
        $publishedEditions = Edition::query()->published()->count();
        $draftEditions = $totalEditions - $publishedEditions;

        $totalPages = Page::query()->count();
        $totalHotspots = PageHotspot::query()->count();
        $totalAdsCount = Ad::query()->count();

        $recentEditions = Edition::query()
            ->withCount('pages')
            ->orderByDesc('edition_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'name', 'edition_date', 'status', 'published_at'])
            ->map(fn (Edition $edition): array => [
                'id' => $edition->id,
                'name' => $edition->name,
                'edition_date' => $edition->edition_date->toDateString(),
                'status' => $edition->status,
                'pages_count' => $edition->pages_count,
            ])
            ->all();

        return Inertia::render('EpAdmin/Dashboard', [
            'todayPagesCount' => $todayPagesCount,
            'activeAdsCount' => Ad::query()->active()->count(),
            'totalAdsCount' => $totalAdsCount,
            'totalUsersCount' => User::query()->count(),
            'totalCategoriesCount' => Category::query()->count(),
            'totalEditions' => $totalEditions,
            'publishedEditions' => $publishedEditions,
            'draftEditions' => $draftEditions,
            'totalPages' => $totalPages,
            'totalHotspots' => $totalHotspots,
            'recentEditions' => $recentEditions,
        ]);
    }
}
