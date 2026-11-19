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

    /** @return array<string, string> [nilai => label] untuk pilihan form */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn(array $carry, self $case) => $carry + [$case->value => $case->label()], []);
    }
}
