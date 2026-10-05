<?php

namespace App\Models;

use App\Enums\FactoryOrderStatus;
use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\FactoryOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FactoryOrder extends Model
{
    /** @use HasFactory<FactoryOrderFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'factory_id',
        'product_id',
        'factory_order_form_id',
        'prepared_by_user_id',
        'product_name',
        'contact_name',
        'quantity',
        'length',
        'pallet_count',
        'due_on',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'pallet_count' => 'integer',
            'due_on' => 'date',
            'status' => FactoryOrderStatus::class,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function sourceFactory(): BelongsTo
    {
        return $this->belongsTo(Factory::class, 'factory_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FactoryOrderForm::class, 'factory_order_form_id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }
}
