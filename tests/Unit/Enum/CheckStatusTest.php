<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\CheckStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckStatusTest extends TestCase
{
    #[Test]
    #[DataProvider('problematicProvider')]
    public function testIsProblematicReturnsExpectedValue(CheckStatus $status, bool $expected): void
    {
        self::assertSame($expected, $status->isProblematic());
    }

    /** @return iterable<string, array{CheckStatus, bool}> */
    public static function problematicProvider(): iterable
    {
        yield 'fail is problematic' => [CheckStatus::Fail, true];
        yield 'warn is problematic' => [CheckStatus::Warn, true];
        yield 'ok is not problematic' => [CheckStatus::Ok, false];
        yield 'unknown is not problematic' => [CheckStatus::Unknown, false];
    }

    #[Test]
    #[DataProvider('priorityProvider')]
    public function testPriorityReturnsExpectedValue(CheckStatus $status, int $expected): void
    {
        self::assertSame($expected, $status->priority());
    }

    /** @return iterable<string, array{CheckStatus, int}> */
    public static function priorityProvider(): iterable
    {
        yield 'fail has priority 3' => [CheckStatus::Fail, 3];
        yield 'warn has priority 2' => [CheckStatus::Warn, 2];
        yield 'unknown has priority 1' => [CheckStatus::Unknown, 1];
        yield 'ok has priority 0' => [CheckStatus::Ok, 0];
    }

    #[Test]
    #[DataProvider('labelProvider')]
    public function testLabelReturnsExpectedValue(CheckStatus $status, string $expected): void
    {
        self::assertSame($expected, $status->label());
    }

    /** @return iterable<string, array{CheckStatus, string}> */
    public static function labelProvider(): iterable
    {
        yield 'ok' => [CheckStatus::Ok, 'OK'];
        yield 'warn' => [CheckStatus::Warn, 'WARN'];
        yield 'fail' => [CheckStatus::Fail, 'FAIL'];
        yield 'unknown' => [CheckStatus::Unknown, 'UNKNOWN'];
    }

    #[Test]
    #[DataProvider('cssClassProvider')]
    public function testCssClassReturnsExpectedValue(CheckStatus $status, string $expected): void
    {
        self::assertSame($expected, $status->cssClass());
    }

    /** @return iterable<string, array{CheckStatus, string}> */
    public static function cssClassProvider(): iterable
    {
        yield 'ok' => [CheckStatus::Ok, 'text-green-600 bg-green-50'];
        yield 'warn' => [CheckStatus::Warn, 'text-orange-600 bg-orange-50'];
        yield 'fail' => [CheckStatus::Fail, 'text-red-600 bg-red-50'];
        yield 'unknown' => [CheckStatus::Unknown, 'text-yellow-600 bg-yellow-50'];
    }

    #[Test]
    #[DataProvider('dotClassProvider')]
    public function testDotClassReturnsExpectedValue(CheckStatus $status, string $expected): void
    {
        self::assertSame($expected, $status->dotClass());
    }

    /** @return iterable<string, array{CheckStatus, string}> */
    public static function dotClassProvider(): iterable
    {
        yield 'ok' => [CheckStatus::Ok, 'bg-green-500'];
        yield 'warn' => [CheckStatus::Warn, 'bg-orange-400'];
        yield 'fail' => [CheckStatus::Fail, 'bg-red-500'];
        yield 'unknown' => [CheckStatus::Unknown, 'bg-yellow-400'];
    }

    #[Test]
    #[DataProvider('textClassProvider')]
    public function testTextClassReturnsExpectedValue(CheckStatus $status, string $expected): void
    {
        self::assertSame($expected, $status->textClass());
    }

    /** @return iterable<string, array{CheckStatus, string}> */
    public static function textClassProvider(): iterable
    {
        yield 'ok' => [CheckStatus::Ok, 'text-green-600'];
        yield 'warn' => [CheckStatus::Warn, 'text-orange-600'];
        yield 'fail' => [CheckStatus::Fail, 'text-red-600'];
        yield 'unknown' => [CheckStatus::Unknown, 'text-yellow-600'];
    }

    #[Test]
    #[DataProvider('cardBorderClassProvider')]
    public function testCardBorderClassReturnsExpectedValue(CheckStatus $status, string $expected): void
    {
        self::assertSame($expected, $status->cardBorderClass());
    }

    /** @return iterable<string, array{CheckStatus, string}> */
    public static function cardBorderClassProvider(): iterable
    {
        yield 'ok' => [CheckStatus::Ok, 'border-l-4 border-green-400'];
        yield 'warn' => [CheckStatus::Warn, 'border-l-4 border-orange-400'];
        yield 'fail' => [CheckStatus::Fail, 'border-l-4 border-red-500'];
        yield 'unknown' => [CheckStatus::Unknown, 'border-l-4 border-yellow-400'];
    }
}
