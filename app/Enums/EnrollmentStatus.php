<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Active = 'aktif';
    case Promoted = 'naik_kelas';
    case Retained = 'tinggal_kelas';
    case Graduated = 'lulus';
    case Transferred = 'mutasi';
    case DroppedOut = 'do';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Promoted => 'Naik Kelas',
            self::Retained => 'Tinggal Kelas',
            self::Graduated => 'Lulus',
            self::Transferred => 'Mutasi',
            self::DroppedOut => 'DO',
        };
    }
}
