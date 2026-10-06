<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\GoodsFlowFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsFlow extends Model
{
    /** @use HasFactory<GoodsFlowFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'recorded_on',
        'store_incoming',
        'store_outgoing',
        'warehouse_incoming',
        'warehouse_outgoing',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'store_incoming' => 'decimal:3',
            'store_outgoing' => 'decimal:3',
            'warehouse_incoming' => 'decimal:3',
            'warehouse_outgoing' => 'decimal:3',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
