<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\CategoryColumnDefinition;
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
            ->with(['parent:id,name', 'columnDefinitions:id'])
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'parent' => $category->parent?->only(['id', 'name']),
                'products_count' => $category->products_count,
                'column_ids' => $category->parent_id === null
                    ? $category->columnDefinitions->pluck('id')->all()
                    : [],
            ]);

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'parentOptions' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'columnGroups' => $this->columnGroups(),
            'canManage' => $request->user()->can('stock.manage'),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $category = Category::query()->create([
            ...$request->safe()->except('column_ids'),
            'sort_order' => $request->integer('sort_order'),
        ]);
        $this->syncColumns($category, $request->validated('column_ids') ?? []);

        return redirect()
            ->route('categories.index')
            ->with('success', 'Kategori oluşturuldu.');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update([
            ...$request->safe()->except('column_ids'),
            'sort_order' => $request->integer('sort_order'),
        ]);
        $this->syncColumns($category, $request->validated('column_ids') ?? []);

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

    /**
     * @param  list<int>  $columnIds
     */
    private function syncColumns(Category $category, array $columnIds): void
    {
        $category->refresh();

        if ($category->parent_id !== null) {
            $category->columnDefinitions()->detach();

            return;
        }

        $category->columnDefinitions()->sync($columnIds);
    }

    /**
     * @return list<array{label: string, columns: list<array{id: int, label: string}>}>
     */
    private function columnGroups(): array
    {
        $labels = [
            'list' => 'Liste',
            'form' => 'Form',
            'action' => 'İşlem',
        ];

        return CategoryColumnDefinition::query()
            ->orderBy('sort_order')
            ->get()
            ->groupBy(fn (CategoryColumnDefinition $definition) => $definition->group->value)
            ->map(fn ($columns, string $group) => [
                'label' => $labels[$group] ?? $group,
                'columns' => $columns->map(fn (CategoryColumnDefinition $definition) => [
                    'id' => $definition->id,
                    'label' => $definition->label,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
