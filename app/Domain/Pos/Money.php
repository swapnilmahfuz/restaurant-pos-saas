<?php

namespace App\Domain\Pos;

final class Money
{
    public const MAX_MINOR = 99_999_999_999;

    public const INPUT = 'regex:/\A[0-9]{1,9}(?:\.[0-9]{1,2})?\z/';

    public static function minor(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function display(int $minor): string
    {
        return intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
