<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'client_id',
        'user_id',
        'status',
        'quote_date',
        'valid_until',
        'currency',
        'tax_rate',
        'subtotal',
        'tax_amount',
        'total',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'quote_date' => 'date',
            'valid_until' => 'date',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public static function nextNumber(): string
    {
        $year = now()->year;
        $prefix = "Q-{$year}-";

        return DB::transaction(function () use ($prefix, $year) {
            $latest = static::query()
                ->withTrashed()
                ->where('number', 'like', "{$prefix}%")
                ->orderByDesc('number')
                ->value('number');

            $sequence = 1;
            if (is_string($latest) && preg_match('/^Q-'.$year.'-(\d+)$/', $latest, $matches) === 1) {
                $sequence = ((int) $matches[1]) + 1;
            }

            return sprintf('%s%04d', $prefix, $sequence);
        });
    }

    public function recalculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('line_total');
        $taxRate = (float) $this->tax_rate;
        $taxAmount = round($subtotal * ($taxRate / 100), 2);
        $total = round($subtotal + $taxAmount, 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ])->save();
    }
}
