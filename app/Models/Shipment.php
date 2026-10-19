<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Enums\ShipmentType;
use App\Models\Concerns\AssignsCurrentCompany;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use AssignsCurrentCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'client_id',
        'vehicle_id',
        'quote_id',
        'user_id',
        'prepared_by',
        'invoiced_by',
        'status',
        'ship_date',
        'delivery_date',
        'vehicle_plate',
        'driver_name',
        'shipping_address',
        'ship_type',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'ship_type' => ShipmentType::class,
            'ship_date' => 'date',
            'delivery_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preparedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function invoicedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invoiced_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return list<string>
     */
    public static function boardRelations(): array
    {
        return [
            'client:id,name',
            'vehicle:id,name',
            'user:id,name',
            'items.product:id,name,category_id',
            'items.product.category:id,name,parent_id',
            'items.product.category.parent:id,name',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toBoardCard(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'ship_date' => $this->ship_date?->format('d.m.Y'),
            'ship_type' => $this->ship_type?->label(),
            'notes' => $this->notes,
            'creator_name' => $this->user?->name,
            'vehicle' => $this->vehicle?->name,
            'client' => [
                'id' => $this->client?->id,
                'name' => $this->client?->name,
            ],
            'items' => $this->items->map(fn (ShipmentItem $item) => [
                'id' => $item->id,
                'product_name' => $item->product?->name,
                'kind' => $item->product?->category?->parent?->name
                    ?? $item->product?->category?->name,
                'quantity_piece' => $item->quantity_piece,
                'unit_price' => $item->unit_price,
            ])->values(),
        ];
    }
}
