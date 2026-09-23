<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'client_id',
        'quote_id',
        'user_id',
        'status',
        'ship_date',
        'delivery_date',
        'vehicle_plate',
        'driver_name',
        'shipping_address',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'ship_date' => 'date',
            'delivery_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public static function nextNumber(): string
    {
        $year = now()->year;
        $prefix = "S-{$year}-";

        return DB::transaction(function () use ($prefix, $year) {
            $latest = static::query()
                ->withTrashed()
                ->where('number', 'like', "{$prefix}%")
                ->orderByDesc('number')
                ->value('number');

            $sequence = 1;
            if (is_string($latest) && preg_match('/^S-'.$year.'-(\d+)$/', $latest, $matches) === 1) {
                $sequence = ((int) $matches[1]) + 1;
            }

            return sprintf('%s%04d', $prefix, $sequence);
        });
    }
}
