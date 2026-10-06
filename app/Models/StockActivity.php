<?php

namespace App\Models;

use App\Enums\StockActivityPlace;
use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\StockActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockActivity extends Model
{
    /** @use HasFactory<StockActivityFactory> */
    use AssignsCurrentCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'product_id',
        'user_id',
        'place',
        'previous_quantity',
        'new_quantity',
        'note',
        'recorded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'place' => StockActivityPlace::class,
            'previous_quantity' => 'integer',
            'new_quantity' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
