<?php

namespace App\Enums;

enum StockActivityPlace: int
{
    case Store = 0;
    case Warehouse = 1;
    case Pallet = 2;

    public function label(): string
    {
        return match ($this) {
            self::Store => 'Mağaza',
            self::Warehouse => 'Depo',
            self::Pallet => 'Palet',
        };
    }
}
