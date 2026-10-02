<?php

namespace App\Models;

use Database\Factories\CustomOrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomOrderItem extends Model
{
    /** @use HasFactory<CustomOrderItemFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'custom_order_id',
        'factory_id',
        'product_name',
        'length',
        'quantity',
        'unit_price',
        'due_on',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'due_on' => 'date',
        ];
    }

    public function customOrder(): BelongsTo
    {
        return $this->belongsTo(CustomOrder::class);
    }

    public function sourceFactory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'factory_id')->withTrashed();
    }
}
