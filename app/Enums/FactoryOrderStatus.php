<?php

namespace App\Enums;

enum FactoryOrderStatus: string
{
    case Open = 'open';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Açık',
            self::Received => 'Teslim alındı',
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
