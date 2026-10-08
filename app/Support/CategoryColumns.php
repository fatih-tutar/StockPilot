<?php

namespace App\Support;

use App\Enums\UserAccessLevel;
use App\Models\Category;
use App\Models\CategoryColumnDefinition;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;

class CategoryColumns
{
    /**
     * @return Collection<int, CategoryColumnDefinition>
     */
    public static function definitions(?Category $category): Collection
    {
        if ($category === null) {
            return collect();
        }

        return $category->activeColumnDefinitions()->get();
    }

    /**
     * Column names this category shows, after the person's own gates.
     *
     * @return list<string>
     */
    public static function visibleNames(?Category $category, User $user): array
    {
        return self::definitions($category)
            ->pluck('name')
            ->filter(fn (string $name): bool => self::allowed($name, $user))
            ->values()
            ->all();
    }

    public static function allowed(string $name, User $user): bool
    {
        $columns = AccessRoles::visibleColumns($user);

        return match ($name) {
            'quantity' => $columns['piece'],
            'pallet' => $columns['pallet'],
            'warehouse_quantity' => $columns['alkop'],
            'purchase_price' => $columns['purchase'],
            'sales_price', 'manual_sales' => $columns['sale'],
            'factory' => $user->can('factories.view'),
            'total' => self::seesTotals($user),
            'offer_button' => $user->can('quotes.manage'),
            'order_button' => $user->can('factory_orders.manage'),
            'shipment_button' => $user->can('shipments.manage'),
            'edit_button' => $user->can('stock.manage'),
            default => true,
        };
    }

    public static function attributeFor(string $name): ?string
    {
        return match ($name) {
            'product_code' => 'sku',
            'quantity' => 'quantity_piece',
            'pallet' => 'quantity_pallet',
            'warehouse_quantity' => 'warehouse_quantity',
            'shelf' => 'shelf',
            'unit_weight' => 'unit_weight_kg',
            'size_measure' => 'length_measure',
            'purchase_price' => 'purchase_price',
            'sales_price', 'manual_sales' => 'sale_price',
            'factory' => 'factory_id',
            'customer_name' => 'customer_name',
            'due_date' => 'due_on',
            'order_quantity' => 'default_order_quantity',
            'warning_count' => 'low_stock_threshold',
            'warehouse_warning_count' => 'warehouse_low_stock_threshold',
            default => null,
        };
    }

    public static function listValue(Product $product, string $name): mixed
    {
        return match ($name) {
            'product_code' => $product->sku,
            'quantity' => $product->quantity_piece,
            'pallet' => $product->quantity_pallet,
            'warehouse_quantity' => $product->warehouse_quantity,
            'shelf' => $product->shelf,
            'unit_weight' => $product->unit_weight_kg,
            'order_weight' => self::weight($product, $product->default_order_quantity),
            'size_measure' => $product->length_measure,
            'total' => self::weight($product, $product->quantity_piece),
            'purchase_price' => $product->purchase_price,
            'sales_price' => $product->sale_price,
            'factory' => $product->sourceFactory?->name,
            'customer_name' => $product->customer_name,
            'due_date' => $product->due_on?->format('d.m.Y'),
            'order_quantity' => $product->default_order_quantity,
            'warning_count' => $product->low_stock_threshold,
            'warehouse_warning_count' => $product->warehouse_low_stock_threshold,
            default => null,
        };
    }

    private static function seesTotals(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->access_level === UserAccessLevel::Manager
            || $user->can('totals.view');
    }

    private static function weight(Product $product, ?int $quantity): ?float
    {
        if ($product->unit_weight_kg === null || $quantity === null) {
            return null;
        }

        return round((float) $product->unit_weight_kg * $quantity, 3);
    }
}
