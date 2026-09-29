<?php

namespace App\Models;

use App\Enums\CategoryColumnGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CategoryColumnDefinition extends Model
{
    protected $fillable = [
        'name',
        'label',
        'group',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'group' => CategoryColumnGroup::class,
            'sort_order' => 'integer',
        ];
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_columns')->withTimestamps();
    }
}
