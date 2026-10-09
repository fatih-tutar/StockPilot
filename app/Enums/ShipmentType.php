<?php

namespace App\Enums;

enum ShipmentType: string
{
    case CustomerCaglayan = 'Müşteri Çağlayan';
    case CustomerAlkop = 'Müşteri Alkop';
    case OwnDelivery = 'Tarafımızca sevk';
    case WarehouseDelivery = 'Ambara tarafımızca sevk';
    case Cargo = 'Kargo Teslim';

    public function label(): string
    {
        return $this->value;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases(),
        );
    }
}
