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

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Graduated => 'info',
            self::Transferred => 'warning',
            self::DroppedOut => 'danger',
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
