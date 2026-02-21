<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Edition;
use App\Models\Page;
use App\Support\EpaperData;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class EditionManageController extends Controller
{
    public function index(Request $request): Response
    {
        $rawDate = $request->query('date');
        $date = is_string($rawDate) ? trim($rawDate) : '';

        $editionData = null;
        $pagesData = [];
        $dateError = null;

        if ($date !== '') {
            $normalizedDate = $this->normalizeDate($date);

            if ($normalizedDate === null) {
                $dateError = 'Invalid date format. Use YYYY-MM-DD.';
            } else {
                $edition = $this->findOrCreateEditionForDate(
                    $normalizedDate,
                    (int) $request->user()->id,
                );

                $edition->load([
                    'pages' => fn ($query) => $query
                        ->with(['category', 'hotspots'])
                        ->orderBy('page_no'),
                ]);

                $editionData = [
                    'id' => $edition->id,
                    'edition_date' => $edition->edition_date->toDateString(),
                    'status' => $edition->status,
                    'published_at' => $edition->published_at?->toISOString(),
                ];

                /** @var Collection<int, Page> $editionPages */
                $editionPages = $edition->pages;

                $pagesData = $editionPages
                    ->map(fn (Page $page): array => EpaperData::mapPage($page))
                    ->values()
                    ->all();

                $date = $edition->edition_date->toDateString();
            }
        }

        /** @var Collection<int, Category> $categories */
        $categories = Category::query()
            ->orderBy('position')
            ->get();

        return Inertia::render('EpAdmin/Editions/Manage', [
            'date' => $date,
            'date_error' => $dateError,
            'edition' => $editionData,
            'pages' => $pagesData,
            'categories' => $categories
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'position' => $category->position,
                    'is_active' => $category->is_active,
                ])
                ->values()
                ->all(),
        ]);
    }

    private function findOrCreateEditionForDate(CarbonImmutable $date, int $userId): Edition
    {
        $dateString = $date->toDateString();

        $existingEdition = Edition::query()
            ->forDate($dateString)
            ->first();

        if ($existingEdition instanceof Edition) {
            return $existingEdition;
        }

        try {
            return Edition::query()->create([
                'edition_date' => $date,
                'status' => Edition::STATUS_DRAFT,
                'created_by' => $userId,
            ]);
        } catch (UniqueConstraintViolationException) {
            return Edition::query()
                ->forDate($dateString)
                ->firstOrFail();
        }
    }

    private function normalizeDate(string $value): ?CarbonImmutable
    {
        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $value);

            if ($date === false || $date->format('Y-m-d') !== $value) {
                return null;
            }

            return $date->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
