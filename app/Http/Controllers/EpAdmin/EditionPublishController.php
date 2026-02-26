<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EpAdmin\EditionPublishRequest;
use App\Models\Edition;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EditionPublishController extends Controller
{
    public function index(Request $request): Response
    {
        $rawDate = $request->query('date');
        $date = is_string($rawDate) ? trim($rawDate) : '';
        $selectedEditionId = $this->parseEditionId($request->query('edition_id'));
        $dateError = null;
        $dateNotice = null;
        $selectedEdition = null;
        $editionsForDate = collect();
        $resolvedSelectedEditionId = null;

        if ($selectedEditionId !== null) {
            $selectedEdition = Edition::query()->find($selectedEditionId);
        }

        if ($date !== '') {
            $normalizedDate = $this->normalizeDate($date);

            if ($normalizedDate === null) {
                $dateError = 'Invalid date format. Use YYYY-MM-DD.';
            } else {
                $date = $normalizedDate->toDateString();
            }
        }

        if ($date === '' && $selectedEdition instanceof Edition) {
            $date = $selectedEdition->edition_date->toDateString();
        }

        if ($date !== '') {
            /** @var Collection<int, Edition> $editionsForDate */
            $editionsForDate = Edition::query()
                ->forDate($date)
                ->withCount('pages')
                ->with(['pages' => fn ($query) => $query->select(['id', 'edition_id', 'page_no'])])
                ->orderByDesc('id')
                ->get();

            if ($editionsForDate->isEmpty() && $dateError === null) {
                $dateNotice = sprintf('No editions found for %s. Create one from Manage Pages first.', $date);
            }

            if ($selectedEditionId !== null) {
                $matchedEdition = $editionsForDate->firstWhere('id', $selectedEditionId);

                if ($matchedEdition instanceof Edition) {
                    $resolvedSelectedEditionId = $matchedEdition->id;
                } elseif ($dateError === null) {
                    $dateNotice = sprintf('Edition #%d was not found for %s.', $selectedEditionId, $date);
                }
            }
        } elseif ($selectedEditionId !== null && $dateError === null) {
            $dateNotice = sprintf('Edition #%d was not found.', $selectedEditionId);
        }

        return Inertia::render('EpAdmin/Editions/Publish', [
            'date' => $date,
            'date_error' => $dateError,
            'date_notice' => $dateNotice,
            'selected_edition_id' => $resolvedSelectedEditionId,
            'editions_for_date' => $editionsForDate
                ->map(function (Edition $edition): array {
                    $publishReadiness = $this->evaluatePublishReadiness($edition);

                    return [
                        'id' => $edition->id,
                        'edition_date' => $edition->edition_date->toDateString(),
                        'name' => $edition->name,
                        'status' => $edition->status,
                        'published_at' => $edition->published_at?->toISOString(),
                        'pages_count' => (int) $edition->pages_count,
                        'publish_readiness' => $publishReadiness,
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    public function publish(EditionPublishRequest $request): RedirectResponse
    {
        $edition = Edition::query()->findOrFail((int) $request->validated('edition_id'));

        $this->authorize('publish', $edition);

        $readiness = $this->evaluatePublishReadiness($edition);

        if (! $readiness['is_ready']) {
            throw ValidationException::withMessages([
                'edition_id' => $readiness['blockers'],
            ]);
        }

        $edition->update([
            'status' => Edition::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return redirect()
            ->route('epadmin.editions.publish.index', [
                'date' => $edition->edition_date->toDateString(),
                'edition_id' => $edition->id,
            ])
            ->with('success', 'Edition published successfully.');
    }

    public function unpublish(EditionPublishRequest $request): RedirectResponse
    {
        $edition = Edition::query()->findOrFail((int) $request->validated('edition_id'));

        $this->authorize('publish', $edition);

        $edition->update([
            'status' => Edition::STATUS_DRAFT,
            'published_at' => null,
        ]);

        return redirect()
            ->route('epadmin.editions.publish.index', [
                'date' => $edition->edition_date->toDateString(),
                'edition_id' => $edition->id,
            ])
            ->with('success', 'Edition moved back to draft.');
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

    /**
     * @return array{is_ready: bool, blockers: array<int, string>}
     */
    private function evaluatePublishReadiness(Edition $edition): array
    {
        if ($edition->relationLoaded('pages')) {
            /** @var Collection<int, int> $pageNumbers */
            $pageNumbers = $edition->getRelation('pages')
                ->pluck('page_no')
                ->map(fn (mixed $value): int => (int) $value)
                ->values();
        } else {
            /** @var Collection<int, int> $pageNumbers */
            $pageNumbers = $edition->pages()
                ->orderBy('page_no')
                ->pluck('page_no')
                ->map(fn (mixed $value): int => (int) $value)
                ->values();
        }

        $blockers = [];

        if ($pageNumbers->isEmpty()) {
            return [
                'is_ready' => false,
                'blockers' => ['Add at least one page before publishing.'],
            ];
        }

        $pageNoCounts = [];

        foreach ($pageNumbers as $pageNo) {
            $pageNoCounts[$pageNo] = ($pageNoCounts[$pageNo] ?? 0) + 1;
        }

        $duplicatePageNumbers = collect($pageNoCounts)
            ->filter(fn (int $count): bool => $count > 1)
            ->keys()
            ->map(fn (mixed $value): int => (int) $value)
            ->sort()
            ->values()
            ->all();

        if ($duplicatePageNumbers !== []) {
            $hasSingleDuplicate = count($duplicatePageNumbers) === 1;

            $blockers[] = sprintf(
                'Duplicate %s found: %s. Resolve duplicates before publishing.',
                $hasSingleDuplicate ? 'page number' : 'page numbers',
                $this->summarizePageNumbers($duplicatePageNumbers),
            );
        }

        $uniquePageNumbers = $pageNumbers
            ->unique()
            ->sort()
            ->values();

        $maxPageNo = (int) ($uniquePageNumbers->last() ?? 0);
        $missingPageNumbers = [];
        $presentPageLookup = $uniquePageNumbers->flip();

        for ($pageNo = 1; $pageNo <= $maxPageNo; $pageNo++) {
            if (! $presentPageLookup->has($pageNo)) {
                $missingPageNumbers[] = $pageNo;
            }
        }

        if ($missingPageNumbers !== []) {
            $hasSingleGap = count($missingPageNumbers) === 1;

            $blockers[] = sprintf(
                'Page numbering has %s: missing %s %s.',
                $hasSingleGap ? 'a gap' : 'gaps',
                $hasSingleGap ? 'page' : 'pages',
                $this->summarizePageNumbers($missingPageNumbers),
            );
        }

        return [
            'is_ready' => $blockers === [],
            'blockers' => $blockers,
        ];
    }

    /**
     * @param  array<int, int>  $pageNumbers
     */
    private function summarizePageNumbers(array $pageNumbers, int $maxItems = 8): string
    {
        $visible = array_slice($pageNumbers, 0, $maxItems);
        $summary = implode(', ', $visible);
        $remainingCount = count($pageNumbers) - count($visible);

        if ($remainingCount <= 0) {
            return $summary;
        }

        return sprintf('%s, and %d more', $summary, $remainingCount);
    }
}
