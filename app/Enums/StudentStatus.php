<?php

namespace App\Enums;

enum StudentStatus: string
{
    case Active = 'aktif';
    case Graduated = 'lulus';
    case Transferred = 'mutasi';
    case DroppedOut = 'do';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Graduated => 'Lulus',
            self::Transferred => 'Mutasi',
            self::DroppedOut => 'DO',
        };
    }
}
