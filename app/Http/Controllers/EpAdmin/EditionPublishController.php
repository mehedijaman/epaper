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

        $editionData = null;
        $publishReadiness = null;
        $dateError = null;
        $dateNotice = null;

        if ($date !== '') {
            $normalizedDate = $this->normalizeDate($date);

            if ($normalizedDate === null) {
                $dateError = 'Invalid date format. Use YYYY-MM-DD.';
            } else {
                $edition = Edition::query()
                    ->forDate($normalizedDate->toDateString())
                    ->first();

                if (! $edition instanceof Edition) {
                    $dateNotice = sprintf(
                        'No edition found for %s. Create it from Manage Pages first.',
                        $normalizedDate->toDateString(),
                    );
                } else {
                    $edition->loadCount('pages');

                    $editionData = [
                        'id' => $edition->id,
                        'edition_date' => $edition->edition_date->toDateString(),
                        'status' => $edition->status,
                        'published_at' => $edition->published_at?->toISOString(),
                        'pages_count' => (int) $edition->pages_count,
                    ];
                    $publishReadiness = $this->evaluatePublishReadiness($edition);
                }

                $date = $normalizedDate->toDateString();
            }
        }

        return Inertia::render('EpAdmin/Editions/Publish', [
            'date' => $date,
            'date_error' => $dateError,
            'date_notice' => $dateNotice,
            'edition' => $editionData,
            'publish_readiness' => $publishReadiness,
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
            ->route('epadmin.editions.publish.index', ['date' => $edition->edition_date->toDateString()])
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
            ->route('epadmin.editions.publish.index', ['date' => $edition->edition_date->toDateString()])
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

    /**
     * @return array{is_ready: bool, blockers: array<int, string>}
     */
    private function evaluatePublishReadiness(Edition $edition): array
    {
        /** @var Collection<int, int> $pageNumbers */
        $pageNumbers = $edition->pages()
            ->orderBy('page_no')
            ->pluck('page_no')
            ->map(fn (mixed $value): int => (int) $value)
            ->values();

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
