<?php

namespace App\Models;

use App\Enums\OfferListStatus;
use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\OfferListEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferListEntry extends Model
{
    /** @use HasFactory<OfferListEntryFactory> */
    use AssignsCurrentCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'customer_name',
        'contact_name',
        'product_quantity',
        'price',
        'factory_name',
        'factory_price',
        'offered_by_user_id',
        'notes',
        'offered_at',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
            'status' => OfferListStatus::class,
        ];
    }

    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_by_user_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
