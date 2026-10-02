<?php

namespace App\Models;

use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\CustomerVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerVisit extends Model
{
    /** @use HasFactory<CustomerVisitFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_visit_category_id',
        'city',
        'district',
        'customer_name',
        'contact_name',
        'phone',
        'visited_on',
        'planned_on',
        'address',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'visited_on' => 'date',
            'planned_on' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CustomerVisitCategory::class, 'customer_visit_category_id')->withTrashed();
    }
}
