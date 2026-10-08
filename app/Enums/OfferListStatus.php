<?php

namespace App\Enums;

enum OfferListStatus: string
{
    case Open = 'open';
    case ArchivedPositive = 'archived_positive';
    case ArchivedNegative = 'archived_negative';
    case Removed = 'removed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Açık',
            self::ArchivedPositive => 'Arşiv +',
            self::ArchivedNegative => 'Arşiv −',
            self::Removed => 'Silinmiş',
        };
    }

    public static function fromLegacy(int $value): self
    {
        return match ($value) {
            1 => self::ArchivedPositive,
            2 => self::ArchivedNegative,
            3 => self::Removed,
            default => self::Open,
        };
    }

    /**
     * @return list<self>
     */
    public static function archived(): array
    {
        return [self::ArchivedPositive, self::ArchivedNegative];
    }
}
