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
}
