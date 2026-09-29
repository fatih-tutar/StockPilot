<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'letterhead',
        'logo',
        'price_list_visible',
        'usd_rate',
        'lme_rate',
    ];

    protected function casts(): array
    {
        return [
            'price_list_visible' => 'boolean',
            'usd_rate' => 'decimal:4',
            'lme_rate' => 'decimal:4',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
