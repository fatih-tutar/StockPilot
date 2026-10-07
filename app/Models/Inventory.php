<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'category_id',
        'code',
        'dimension_1',
        'dimension_2',
        'dimension_3',
        'density',
        'factory_name',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
