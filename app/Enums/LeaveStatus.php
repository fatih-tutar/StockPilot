<?php

namespace App\Enums;

enum LeaveStatus: int
{
    case Pending = 0;
    case Approved = 1;
    case Rejected = 2;
    case Cancelled = 3;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Onay bekleniyor',
            self::Approved => 'Onaylandı',
            self::Rejected => 'Reddedildi',
            self::Cancelled => 'İptal',
        };
    }

    public function countsAsLeave(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }
}
