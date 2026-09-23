<?php

namespace App\Actions\Stock;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdjustProductStock
{
    /**
     * Apply a stock delta and write an audit movement in one transaction.
     *
     * @param  array{quantity_piece_delta?: int, quantity_pallet_delta?: int, type?: string, note?: string|null}  $data
     */
    public function handle(Product $product, array $data, ?User $user = null): StockMovement
    {
        $pieceDelta = (int) ($data['quantity_piece_delta'] ?? 0);
        $palletDelta = (int) ($data['quantity_pallet_delta'] ?? 0);

        if ($pieceDelta === 0 && $palletDelta === 0) {
            throw new InvalidArgumentException('At least one stock delta must be non-zero.');
        }

        return DB::transaction(function () use ($product, $data, $user, $pieceDelta, $palletDelta) {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $nextPiece = $locked->quantity_piece + $pieceDelta;
            $nextPallet = $locked->quantity_pallet + $palletDelta;

            if ($nextPiece < 0 || $nextPallet < 0) {
                throw new InvalidArgumentException('Stock quantities cannot go below zero.');
            }

            $locked->update([
                'quantity_piece' => $nextPiece,
                'quantity_pallet' => $nextPallet,
            ]);

            return StockMovement::query()->create([
                'product_id' => $locked->id,
                'user_id' => $user?->id,
                'type' => $data['type'] ?? StockMovement::TYPE_ADJUSTMENT,
                'quantity_piece_delta' => $pieceDelta,
                'quantity_pallet_delta' => $palletDelta,
                'note' => $data['note'] ?? null,
            ]);
        });
    }
}
