<?php

namespace App\Models;

use Database\Factories\ShipmentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItem extends Model
{
    /** @use HasFactory<ShipmentItemFactory> */
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'product_id',
        'description',
        'quantity_piece',
        'quantity_pallet',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity_piece' => 'integer',
            'quantity_pallet' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
