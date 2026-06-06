<?php

declare(strict_types=1);

namespace App\Enum;

enum CheckStatus: string
{
    case Ok = 'ok';
    case Fail = 'fail';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Fail => 'FAIL',
            self::Unknown => 'UNKNOWN',
        };
    }

    public function cssClass(): string
    {
        return match ($this) {
            self::Ok => 'text-green-600 bg-green-50',
            self::Fail => 'text-red-600 bg-red-50',
            self::Unknown => 'text-yellow-600 bg-yellow-50',
        };
    }

    public function cardBorderClass(): string
    {
        return match ($this) {
            self::Ok => 'border-l-4 border-green-400',
            self::Fail => 'border-l-4 border-red-500',
            self::Unknown => 'border-l-4 border-yellow-400',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::Ok => 'bg-green-500',
            self::Fail => 'bg-red-500',
            self::Unknown => 'bg-yellow-400',
        };
    }

    public function priority(): int
    {
        return match ($this) {
            self::Fail => 2,
            self::Unknown => 1,
            self::Ok => 0,
        };
    }
}
