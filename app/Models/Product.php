<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'sku',
        'shelf',
        'name',
        'description',
        'unit_weight_kg',
        'length_measure',
        'purchase_price',
        'sale_price',
        'factory_id',
        'customer_name',
        'due_on',
        'pack_quantity',
        'company_id',
        'default_order_quantity',
        'quantity_piece',
        'quantity_pallet',
        'warehouse_quantity',
        'low_stock_threshold',
        'warehouse_low_stock_threshold',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'unit_weight_kg' => 'decimal:3',
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'due_on' => 'date',
            'pack_quantity' => 'integer',
            'company_id' => 'integer',
            'default_order_quantity' => 'integer',
            'quantity_piece' => 'integer',
            'quantity_pallet' => 'integer',
            'warehouse_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'warehouse_low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sourceFactory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function moldNumbers(): HasMany
    {
        return $this->hasMany(MoldNumber::class);
    }

    public function isLowStock(): bool
    {
        if ($this->low_stock_threshold === null) {
            return false;
        }

        return $this->quantity_piece <= $this->low_stock_threshold;
    }
}
