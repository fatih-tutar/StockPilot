<?php

namespace App\Enums;

enum UserAccessLevel: string
{
    case Staff = 'staff';
    case Supervisor = 'supervisor';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Personel',
            self::Supervisor => 'Yetkili',
            self::Manager => 'Yönetici',
        };
    }

    public static function fromLegacy(int $type): self
    {
        return match ($type) {
            1 => self::Supervisor,
            2 => self::Manager,
            default => self::Staff,
        };
    }
}
