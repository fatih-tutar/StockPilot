<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\CustomerVisitCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerVisitCategory extends Model
{
    /** @use HasFactory<CustomerVisitCategoryFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(CustomerVisit::class);
    }
}
