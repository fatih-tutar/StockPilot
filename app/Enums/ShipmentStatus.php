<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Taslak',
            self::Scheduled => 'Planlandı',
            self::InTransit => 'Yolda',
            self::Delivered => 'Teslim edildi',
            self::Cancelled => 'İptal',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::cases(),
        );
    }
}
