<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    protected $fillable = [
        'product_id',
        'user_id',
        'type',
        'quantity_piece_delta',
        'quantity_pallet_delta',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity_piece_delta' => 'integer',
            'quantity_pallet_delta' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
