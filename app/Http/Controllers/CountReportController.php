<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CountReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('count_reports.view');

        $place = $request->string('place')->toString() === 'alkop' ? 'alkop' : 'caglayan';

        $products = Product::query()
            ->with(['category.parent'])
            ->whereHas('category', fn ($query) => $query->whereNotNull('parent_id'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $groups = [];

        foreach ($products as $product) {
            $subcategory = $product->category;
            $main = $subcategory?->parent;

            if ($subcategory === null || $main === null) {
                continue;
            }

            if (! isset($groups[$subcategory->id])) {
                $groups[$subcategory->id] = [
                    'main_id' => $main->id,
                    'main' => $main->name,
                    'sub' => $subcategory->name,
                    'products' => [],
                ];
            }

            $groups[$subcategory->id]['products'][] = [
                'name' => $product->name,
                'piece' => $product->quantity_piece,
                'pallet' => $product->quantity_pallet,
                'warehouse' => $product->warehouse_quantity,
                'shelf' => $product->shelf,
            ];
        }

        $reportGroups = array_values($groups);
        usort($reportGroups, function (array $left, array $right): int {
            return [$left['main_id'], $left['sub']] <=> [$right['main_id'], $right['sub']];
        });

        foreach ($reportGroups as &$group) {
            unset($group['main_id']);
        }
        unset($group);

        return Inertia::render('CountReports/Index', [
            'groups' => $reportGroups,
            'place' => $place,
            'title' => $place === 'alkop' ? 'Alkop Sayım Raporu' : 'Çağlayan Sayım Raporu',
            'reportDate' => now()->timezone('Europe/Istanbul')->format('d.m.Y'),
        ]);
    }
}
