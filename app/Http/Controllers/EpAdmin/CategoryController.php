<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EpAdmin\CategoryStoreRequest;
use App\Http\Requests\EpAdmin\CategoryUpdateRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        /** @var Collection<int, Category> $categories */
        $categories = Category::query()
            ->withCount('pages')
            ->orderBy('position')
            ->get();

        return Inertia::render('EpAdmin/Categories/Index', [
            'categories' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'position' => $category->position,
                'is_active' => $category->is_active,
                'pages_count' => (int) $category->pages_count,
            ])->values(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('epadmin.categories.index');
    }

    public function store(CategoryStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $orderedCategoryIds = $this->getOrderedCategoryIdsForResequence();

            if ($orderedCategoryIds->isNotEmpty()) {
                $this->applySequentialPositions($orderedCategoryIds);
            }

            Category::query()->create([
                'name' => (string) $validated['name'],
                'is_active' => (bool) $validated['is_active'],
                'position' => $orderedCategoryIds->count() + 1,
            ]);
        });

        return redirect()
            ->route('epadmin.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(Category $category): RedirectResponse
    {
        return redirect()->route('epadmin.categories.index');
    }

    public function update(CategoryUpdateRequest $request, Category $category): RedirectResponse
    {
        $validated = $request->validated();

        $category->update([
            'name' => (string) $validated['name'],
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()
            ->route('epadmin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ordered_ids' => ['required_without:positions', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer', 'distinct', 'exists:categories,id'],
            'positions' => ['required_without:ordered_ids', 'array', 'min:1'],
            'positions.*.id' => ['required', 'integer', 'distinct', 'exists:categories,id'],
            'positions.*.position' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        $orderedIds = collect($validated['ordered_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        if ($orderedIds->isEmpty()) {
            $orderedIds = collect($validated['positions'] ?? [])
                ->sortBy(fn (array $item): int => (int) $item['position'])
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values();
        }

        if ($orderedIds->isEmpty()) {
            throw ValidationException::withMessages([
                'ordered_ids' => 'Order payload is required.',
            ]);
        }

        $existingCategoryIds = Category::query()
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values();
        $requestedCategoryIds = $orderedIds->sort()->values();

        if (
            $existingCategoryIds->count() !== $requestedCategoryIds->count()
            || $existingCategoryIds->all() !== $requestedCategoryIds->all()
        ) {
            throw ValidationException::withMessages([
                'ordered_ids' => 'Reorder payload is invalid.',
            ]);
        }

        DB::transaction(function () use ($orderedIds): void {
            $this->applySequentialPositions($orderedIds);
        });

        return redirect()
            ->route('epadmin.categories.index')
            ->with('success', 'Categories reordered successfully.');
    }

    public function toggle(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $category->update([
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()
            ->route('epadmin.categories.index')
            ->with('success', 'Category status updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->pages()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'This category is used by one or more pages and cannot be deleted.',
            ]);
        }

        DB::transaction(function () use ($category): void {
            $category->delete();

            $remainingCategoryIds = $this->getOrderedCategoryIdsForResequence();

            if ($remainingCategoryIds->isNotEmpty()) {
                $this->applySequentialPositions($remainingCategoryIds);
            }
        });

        return redirect()
            ->route('epadmin.categories.index')
            ->with('success', 'Category deleted successfully.');
    }

    /**
     * @return Collection<int, int>
     */
    private function getOrderedCategoryIdsForResequence(): Collection
    {
        return Category::query()
            ->lockForUpdate()
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();
    }

    /**
     * @param  Collection<int, int>  $orderedCategoryIds
     */
    private function applySequentialPositions(Collection $orderedCategoryIds): void
    {
        $categoryCount = $orderedCategoryIds->count();
        $tempStart = 65535 - $categoryCount;

        if ($tempStart < 1) {
            throw ValidationException::withMessages([
                'ordered_ids' => 'Too many categories to reorder.',
            ]);
        }

        foreach ($orderedCategoryIds as $index => $categoryId) {
            Category::query()
                ->whereKey($categoryId)
                ->update(['position' => $tempStart + $index + 1]);
        }

        foreach ($orderedCategoryIds as $index => $categoryId) {
            Category::query()
                ->whereKey($categoryId)
                ->update(['position' => $index + 1]);
        }
    }
}
