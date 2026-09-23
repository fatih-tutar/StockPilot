<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Category::class);

        $categories = Category::query()
            ->with('parent:id,name')
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'sort_order' => $category->sort_order,
                'parent' => $category->parent?->only(['id', 'name']),
                'products_count' => $category->products_count,
            ]);

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'parentOptions' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'canManage' => $request->user()->can('stock.manage'),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::query()->create([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order'),
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Kategori oluşturuldu.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order'),
        ]);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Kategori güncellendi.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->products()->exists()) {
            return redirect()
                ->route('categories.index')
                ->with('error', 'Ürünü olan kategori silinemez.');
        }

        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', 'Kategori silindi.');
    }
}
