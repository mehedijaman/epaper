<?php

namespace App\Http\Controllers\EpAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EpAdmin\CategoryStoreRequest;
use App\Http\Requests\EpAdmin\CategoryUpdateRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
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
        Category::query()->create($request->validated());

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
        $category->update($request->validated());

        return redirect()
            ->route('epadmin.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->pages()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'This category is used by one or more pages and cannot be deleted.',
            ]);
        }

        $category->delete();

        return redirect()
            ->route('epadmin.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
