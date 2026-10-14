<?php

declare(strict_types=1);

namespace RuleInk\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuleInk\ValueReader;

/**
 * The value rules that the Evaluator and database adapters share.
 */
final class ValueReaderTest extends TestCase
{
    /**
     * @return array<string, array{mixed, ?bool}>
     */
    public static function booleans(): array
    {
        return [
            'true' => [true, true],
            'one' => [1, true],
            'string one' => ['1', true],
            'false' => [false, false],
            'zero' => [0, false],
            'string zero' => ['0', false],
            'two' => [2, null],
            'string true' => ['true', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('booleans')]
    public function testReadsABoolean(mixed $value, ?bool $expected): void
    {
        $this->assertSame($expected, (new ValueReader())->boolean($value));
    }

    /**
     * @return array<string, array{int|float|string, ?string}>
     */
    public static function decimals(): array
    {
        return [
            'int' => [100, '100'],
            'whole float' => [100.0, '100'],
            'float' => [0.1, '0.1'],
            'float past 14 digits' => [100.00000000000001, '100.00000000000001'],
            'negative zero' => [-0.0, '0'],
            'exponent' => ['1e2', '100'],
            'negative exponent' => ['-0.5e-3', '-0.0005'],
            'spaces' => [' 100 ', '100'],
            'leading and trailing zeros' => ['00100.500', '100.5'],
            'plus sign' => ['+7', '7'],
            'too long to write out' => ['1e1001', null],
            'infinity' => [INF, null],
            'not a number' => ['abc', null],
        ];
    }

    #[DataProvider('decimals')]
    public function testWritesANumberAsAPlainDecimal(int|float|string $number, ?string $expected): void
    {
        $this->assertSame($expected, (new ValueReader())->decimal($number));
    }

    public function testADateTimeWithoutAnOffsetIsInTheReadersTimezone(): void
    {
        $reader = new ValueReader(new DateTimeZone('Asia/Kuala_Lumpur'));

        $this->assertEquals(
            new DateTimeImmutable('2026-10-08T12:00:00Z'),
            $reader->dateTime('2026-10-08 20:00:00'),
        );
    }

    public function testAnOffsetWinsOverTheReadersTimezone(): void
    {
        $reader = new ValueReader(new DateTimeZone('Asia/Kuala_Lumpur'));

        $this->assertEquals(
            new DateTimeImmutable('2026-10-08T20:00:00Z'),
            $reader->dateTime('2026-10-08T20:00:00Z'),
        );
    }

    public function testNoOffsetMeansUtcByDefault(): void
    {
        $this->assertEquals(
            new DateTimeImmutable('2026-10-08T20:00:00Z'),
            (new ValueReader())->dateTime('2026-10-08 20:00:00'),
        );
    }

    public function testGivesTheTimezoneForDateTimesWithoutAnOffset(): void
    {
        $reader = new ValueReader(new DateTimeZone('Asia/Kuala_Lumpur'));

        $this->assertSame('Asia/Kuala_Lumpur', $reader->timezone()->getName());
        $this->assertSame('UTC', (new ValueReader())->timezone()->getName());
    }

    public function testRejectsWhatIsNotADate(): void
    {
        $reader = new ValueReader();

        $this->assertNull($reader->dateTime('tomorrow'));
        $this->assertNull($reader->dateTime('2026-02-30'));
        $this->assertNull($reader->dateTime(1760000000));
    }
}
