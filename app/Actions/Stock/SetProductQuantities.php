<?php

namespace App\Actions\Stock;

use App\Enums\StockActivityPlace;
use App\Models\Product;
use App\Models\StockActivity;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\AccessRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetProductQuantities
{
    /**
     * Replace only the quantities that were sent. A store change is recorded
     * as Mağaza. A warehouse or pallet change is recorded as one Depo line
     * whose quantities are the sum of warehouse and pallet.
     *
     * @param  array{quantity_piece?: int|null, warehouse_quantity?: int|null, quantity_pallet?: int|null}  $data
     */
    public function handle(Product $product, array $data, User $user): void
    {
        $columns = AccessRoles::visibleColumns($user);
        $piece = $this->provided($data, 'quantity_piece', $columns['piece']);
        $warehouse = $this->provided($data, 'warehouse_quantity', $columns['alkop']);
        $pallet = $this->provided($data, 'quantity_pallet', $columns['pallet']);

        if ($piece === null && $warehouse === null && $pallet === null) {
            throw ValidationException::withMessages([
                'quantity_piece' => 'En az bir yeni adet girin.',
            ]);
        }

        DB::transaction(function () use ($product, $user, $piece, $warehouse, $pallet): void {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $previousPiece = $locked->quantity_piece;
            $previousWarehouse = $locked->warehouse_quantity;
            $previousPallet = $locked->quantity_pallet;
            $nextPiece = $piece ?? $previousPiece;
            $nextWarehouse = $warehouse ?? $previousWarehouse;
            $nextPallet = $pallet ?? $previousPallet;
            $pieceChanged = $nextPiece !== $previousPiece;
            $warehouseChanged = $nextWarehouse !== $previousWarehouse;
            $palletChanged = $nextPallet !== $previousPallet;

            if (! $pieceChanged && ! $warehouseChanged && ! $palletChanged) {
                throw ValidationException::withMessages([
                    'quantity_piece' => 'Girilen adetler mevcut değerlerle aynı.',
                ]);
            }

            $locked->update([
                'quantity_piece' => $nextPiece,
                'warehouse_quantity' => $nextWarehouse,
                'quantity_pallet' => $nextPallet,
            ]);

            if ($pieceChanged) {
                $this->record($locked, $user, StockActivityPlace::Store, $previousPiece, $nextPiece);
            }

            if ($warehouseChanged || $palletChanged) {
                $this->record(
                    $locked,
                    $user,
                    StockActivityPlace::Warehouse,
                    $previousWarehouse + $previousPallet,
                    $nextWarehouse + $nextPallet,
                );
            }

            $pieceDelta = $nextPiece - $previousPiece;
            $palletDelta = $nextPallet - $previousPallet;

            if ($pieceDelta !== 0 || $palletDelta !== 0) {
                StockMovement::query()->create([
                    'product_id' => $locked->id,
                    'user_id' => $user->id,
                    'type' => StockMovement::TYPE_ADJUSTMENT,
                    'quantity_piece_delta' => $pieceDelta,
                    'quantity_pallet_delta' => $palletDelta,
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function provided(array $data, string $key, bool $visible): ?int
    {
        if (! $visible || ! array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        return (int) $data[$key];
    }

    private function record(Product $product, User $user, StockActivityPlace $place, int $previous, int $next): void
    {
        StockActivity::query()->create([
            'product_id' => $product->id,
            'user_id' => $user->id,
            'place' => $place,
            'previous_quantity' => $previous,
            'new_quantity' => $next,
            'recorded_at' => now(),
        ]);
    }
}
