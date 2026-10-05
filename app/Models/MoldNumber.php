<?php

namespace App\Models;

use Database\Factories\MoldNumberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoldNumber extends Model
{
    /** @use HasFactory<MoldNumberFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'factory_id',
        'number',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceFactory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }
}
