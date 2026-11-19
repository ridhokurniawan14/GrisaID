<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'L';
    case Female = 'P';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Laki-laki',
            self::Female => 'Perempuan',
        };
    }

    /** @return array<string, string> [nilai => label] untuk pilihan form */
    public static function options(): array
    {
        return array_reduce(self::cases(), fn(array $carry, self $case) => $carry + [$case->value => $case->label()], []);
    }

    /** Terima enum atau nilai mentah (string) dan kembalikan enum-nya. */
    public static function fromState(mixed $state): ?self
    {
        return $state instanceof self ? $state : (is_scalar($state) ? self::tryFrom((string) $state) : null);
    }
}
