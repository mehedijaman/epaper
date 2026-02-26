<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EpAdmin\EditionNameUpdateRequest;
use App\Http\Requests\EpAdmin\EditionUpsertRequest;
use App\Models\Category;
use App\Models\Edition;
use App\Models\Page;
use App\Services\PageImageUploadService;
use App\Support\EpaperData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EditionManageController extends Controller
{
    public function index(Request $request): Response
    {
        $rawDate = $request->query('date');
        $date = is_string($rawDate) ? trim($rawDate) : '';
        $selectedEditionId = $this->parseEditionId($request->query('edition_id'));

        $selectedDate = null;
        $selectedEditionData = null;
        $pagesData = [];
        $dateError = null;
        $dateNotice = null;
        $selectedEdition = null;
        $editionsForDate = collect();

        if ($date !== '') {
            $normalizedDate = $this->normalizeDate($date);

            if ($normalizedDate === null) {
                $dateError = 'Invalid date format. Use YYYY-MM-DD.';
            } else {
                $selectedDate = $normalizedDate->toDateString();
            }
        }

        if ($selectedDate === null && $selectedEditionId !== null) {
            $selectedEdition = Edition::query()->find($selectedEditionId);

            if ($selectedEdition instanceof Edition) {
                $selectedDate = $selectedEdition->edition_date->toDateString();
            }
        }

        if ($selectedDate !== null) {
            /** @var Collection<int, Edition> $editionsForDate */
            $editionsForDate = Edition::query()
                ->forDate($selectedDate)
                ->withCount('pages')
                ->orderByDesc('id')
                ->get();

            if ($editionsForDate->isEmpty() && $dateError === null) {
                $dateNotice = sprintf(
                    'No editions found for %s. Create a new edition to start.',
                    $selectedDate,
                );
            }

            if ($selectedEditionId !== null) {
                $selectedEdition = $editionsForDate->firstWhere('id', $selectedEditionId);

                if (! $selectedEdition instanceof Edition && $dateError === null) {
                    $dateNotice = sprintf(
                        'Edition #%d was not found for %s.',
                        $selectedEditionId,
                        $selectedDate,
                    );
                }
            }
        } elseif ($selectedEditionId !== null && $dateError === null) {
            $dateNotice = sprintf('Edition #%d was not found.', $selectedEditionId);
        }

        if ($selectedEdition instanceof Edition) {
            $selectedEdition->load([
                'pages' => fn ($query) => $query
                    ->with(['category', 'hotspots'])
                    ->orderBy('page_no'),
            ]);

            $selectedEditionData = [
                'id' => $selectedEdition->id,
                'edition_date' => $selectedEdition->edition_date->toDateString(),
                'name' => $selectedEdition->name,
                'status' => $selectedEdition->status,
                'published_at' => $selectedEdition->published_at?->toISOString(),
            ];

            /** @var Collection<int, Page> $editionPages */
            $editionPages = $selectedEdition->pages;

            $pagesData = $editionPages
                ->map(fn (Page $page): array => EpaperData::mapPage($page))
                ->values()
                ->all();
        }

        /** @var Collection<int, Category> $categories */
        $categories = Category::query()
            ->orderBy('position')
            ->get();

        return Inertia::render('EpAdmin/Editions/Manage', [
            'selectedDate' => $selectedDate,
            'dateError' => $dateError,
            'dateNotice' => $dateNotice,
            'selectedEdition' => $selectedEditionData,
            'canDeleteSelectedEdition' => $selectedEdition instanceof Edition
                ? (bool) $request->user()?->can('delete', $selectedEdition)
                : false,
            'editionsForDate' => $editionsForDate
                ->map(fn (Edition $item): array => [
                    'id' => $item->id,
                    'edition_date' => $item->edition_date->toDateString(),
                    'name' => $item->name,
                    'status' => $item->status,
                    'published_at' => $item->published_at?->toISOString(),
                    'pages_count' => (int) $item->pages_count,
                ])
                ->values()
                ->all(),
            'uploadConstraints' => [
                'maxFileSizeKb' => max(1, (int) config('epaper.page_upload_max_file_kb', 15360)),
                'maxFiles' => max(1, (int) config('epaper.page_upload_max_files', 200)),
                'serverUploadMaxBytes' => $this->parseIniSizeToBytes((string) ini_get('upload_max_filesize')),
                'serverPostMaxBytes' => $this->parseIniSizeToBytes((string) ini_get('post_max_size')),
                'serverMaxFileUploads' => max(1, (int) ini_get('max_file_uploads')),
            ],
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

    public function store(EditionUpsertRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $edition = Edition::query()->create([
            'edition_date' => $validated['edition_date'],
            'name' => $this->normalizeName($validated['name'] ?? null),
            'status' => Edition::STATUS_DRAFT,
            'created_by' => (int) $request->user()->id,
        ]);

        return redirect()
            ->route('epadmin.editions.manage', [
                'date' => $edition->edition_date->toDateString(),
                'edition_id' => $edition->id,
            ])
            ->with('success', 'Edition created successfully.');
    }

    public function update(EditionNameUpdateRequest $request, Edition $edition): RedirectResponse
    {
        $this->authorize('update', $edition);

        $validated = $request->validated();
        $name = $this->normalizeName($validated['name'] ?? null);

        $edition->update([
            'name' => $name,
        ]);

        return redirect()
            ->route('epadmin.editions.manage', [
                'date' => $edition->edition_date->toDateString(),
                'edition_id' => $edition->id,
            ])
            ->with('success', $name === null ? 'Edition name cleared successfully.' : 'Edition name updated successfully.');
    }

    public function destroy(Edition $edition, PageImageUploadService $uploadService): RedirectResponse
    {
        $this->authorize('delete', $edition);

        $editionDate = $edition->edition_date->toDateString();
        $editionId = $edition->id;

        DB::transaction(function () use ($edition, $uploadService): void {
            $edition->pages()
                ->select(['id', 'edition_id', 'image_original_path', 'image_large_path', 'image_thumb_path'])
                ->chunkById(100, function (EloquentCollection $pages) use ($uploadService): void {
                    foreach ($pages as $page) {
                        $uploadService->deleteFiles($page);
                    }
                });

            $edition->delete();
        });

        $nextEditionId = Edition::query()
            ->forDate($editionDate)
            ->orderByDesc('id')
            ->value('id');

        $query = ['date' => $editionDate];

        if (is_int($nextEditionId) && $nextEditionId > 0) {
            $query['edition_id'] = $nextEditionId;
        }

        return redirect()
            ->route('epadmin.editions.manage', $query)
            ->with('success', sprintf('Edition %d deleted successfully.', $editionId));
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

    private function parseEditionId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        $parsed = (int) $trimmed;

        return $parsed > 0 ? $parsed : null;
    }

    private function normalizeName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function parseIniSizeToBytes(string $value): int
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return 0;
        }

        $lastChar = strtolower(substr($trimmed, -1));

        if (! in_array($lastChar, ['k', 'm', 'g', 't'], true)) {
            return max(0, (int) $trimmed);
        }

        $numberPart = (float) substr($trimmed, 0, -1);

        if (! is_finite($numberPart) || $numberPart <= 0) {
            return 0;
        }

        $multiplier = match ($lastChar) {
            'k' => 1024,
            'm' => 1024 ** 2,
            'g' => 1024 ** 3,
            't' => 1024 ** 4,
            default => 1,
        };

        return (int) round($numberPart * $multiplier);
    }
}
