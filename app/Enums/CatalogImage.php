<?php

namespace App\Enums;

enum CatalogImage: string
{
    case Primary = 'image_1';
    case Secondary = 'image_2';

    public function label(): string
    {
        return match ($this) {
            self::Primary => 'Fotoğraf 1',
            self::Secondary => 'Fotoğraf 2',
        };
    }
}
