<?php

namespace App\Models;

use App\Enums\CustomOrderStatus;
use App\Enums\DeliveryMethod;
use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\CustomOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomOrder extends Model
{
    /** @use HasFactory<CustomOrderFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'client_id',
        'user_id',
        'delivery_method',
        'status',
        'notes',
        'ordered_at',
    ];

    protected function casts(): array
    {
        return [
            'delivery_method' => DeliveryMethod::class,
            'status' => CustomOrderStatus::class,
            'ordered_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomOrderItem::class)->orderBy('id');
    }
}
