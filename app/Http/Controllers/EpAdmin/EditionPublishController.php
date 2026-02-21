<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EpAdmin\EditionPublishRequest;
use App\Models\Edition;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EditionPublishController extends Controller
{
    public function index(Request $request): Response
    {
        $rawDate = $request->query('date');
        $date = is_string($rawDate) ? trim($rawDate) : '';

        $editionData = null;
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

                $edition->loadCount('pages');

                $editionData = [
                    'id' => $edition->id,
                    'edition_date' => $edition->edition_date->toDateString(),
                    'status' => $edition->status,
                    'published_at' => $edition->published_at?->toISOString(),
                    'pages_count' => (int) $edition->pages_count,
                ];

                $date = $edition->edition_date->toDateString();
            }
        }

        return Inertia::render('EpAdmin/Editions/Publish', [
            'date' => $date,
            'date_error' => $dateError,
            'edition' => $editionData,
        ]);
    }

    public function publish(EditionPublishRequest $request): RedirectResponse
    {
        $edition = Edition::query()->findOrFail((int) $request->validated('edition_id'));

        $this->authorize('publish', $edition);

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
