<?php

namespace App\Models;

use Database\Factories\FactoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Factory extends Model
{
    /** @use HasFactory<FactoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'phone',
        'email',
        'address',
        'labor_cost',
        'fine_labor_cost',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'labor_cost' => 'decimal:2',
            'fine_labor_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
