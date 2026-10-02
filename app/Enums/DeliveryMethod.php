<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case CustomerCaglayan = 'customer_caglayan';
    case CustomerAlkop = 'customer_alkop';
    case WeShip = 'we_ship';
    case WeShipWarehouse = 'we_ship_warehouse';
    case FactoryPickup = 'factory_pickup';

    public function label(): string
    {
        return match ($this) {
            self::CustomerCaglayan => 'Müşteri Çağlayan',
            self::CustomerAlkop => 'Müşteri Alkop',
            self::WeShip => 'Tarafımızca sevk',
            self::WeShipWarehouse => 'Ambar tarafımızca sevk',
            self::FactoryPickup => 'Fabrikadan teslim',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $method) => [
                'value' => $method->value,
                'label' => $method->label(),
            ],
            self::cases(),
        );
    }

    public static function fromLegacy(string|int|null $value): self
    {
        return match ((string) $value) {
            '1' => self::CustomerAlkop,
            '2' => self::WeShip,
            '3' => self::WeShipWarehouse,
            '4' => self::FactoryPickup,
            default => self::CustomerCaglayan,
        };
    }
}
