<?php

namespace App\Enums;

enum FactoryOrderDestination: string
{
    case Piece = 'piece';
    case Warehouse = 'warehouse';
    case Pallet = 'pallet';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'Adet',
            self::Warehouse => 'Depo',
            self::Pallet => 'Palet',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $destination) => [
                'value' => $destination->value,
                'label' => $destination->label(),
            ],
            self::cases(),
        );
    }
}
