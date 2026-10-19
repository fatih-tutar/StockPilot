<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Received = 'received';
    case Preparing = 'preparing';
    case Invoiced = 'invoiced';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Alınan Siparişler',
            self::Preparing => 'Hazırlanan Siparişler',
            self::Invoiced => 'Faturası Kesilenler',
            self::Archived => 'Arşiv',
        };
    }

    /**
     * Active workflow tabs (excludes archive).
     *
     * @return list<self>
     */
    public static function tabs(): array
    {
        return [
            self::Received,
            self::Preparing,
            self::Invoiced,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function tabOptions(): array
    {
        return array_map(
            fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            self::tabs(),
        );
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Received => [self::Preparing],
            self::Preparing => [self::Received, self::Invoiced],
            self::Invoiced => [self::Preparing, self::Archived],
            self::Archived => [self::Invoiced],
        };
    }
}
