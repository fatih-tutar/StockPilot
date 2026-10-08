<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'image',
        'profit_margin',
        'company_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'profit_margin' => 'decimal:2',
            'company_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function columnDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(CategoryColumnDefinition::class, 'category_columns')
            ->withTimestamps()
            ->orderBy('category_column_definitions.sort_order');
    }

    /**
     * Column layout lives on the root category. Children inherit it.
     */
    public function activeColumnDefinitions(): BelongsToMany
    {
        $owner = $this->parent_id === null ? $this : ($this->parent ?? $this);

        return $owner->columnDefinitions();
    }
}
