<?php

namespace App\Enums;

enum GuardianRelation: string
{
    case Father = 'ayah';
    case Mother = 'ibu';
    case Guardian = 'wali';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Ayah',
            self::Mother => 'Ibu',
            self::Guardian => 'Wali',
        };
    }
}
