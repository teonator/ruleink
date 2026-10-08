<?php

declare(strict_types=1);

namespace Ruleink\Tests;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ruleink\ConfiguredExpression;
use Ruleink\Evaluator;
use Ruleink\ExpressionDefinition;
use Ruleink\FieldType;

/**
 * String, boolean and date semantics. Database adapters must reproduce these exactly.
 */
final class TypedExpressionTest extends TestCase
{
    public static function strings(): array
    {
        return [
            'equals same' => ['equals', 'Apple', 'Apple', true],
            'equals is case-sensitive' => ['equals', 'Apple', 'apple', false],
            'equals empty string' => ['equals', '', '', true],
            'equals ignores numeric looseness' => ['equals', '1e1', '10', false],
            'equals needs a string actual' => ['equals', 5, '5', false],
            'equals null actual' => ['equals', null, '', false],
            'contains substring' => ['contains', 'Green Apple', 'Apple', true],
            'contains is case-sensitive' => ['contains', 'Green Apple', 'apple', false],
            'contains empty needle' => ['contains', 'abc', '', true],
            'contains empty needle on null' => ['contains', null, '', false],
            'percent is literal' => ['contains', '100%', '%', true],
            'percent is not a wildcard' => ['contains', 'abc', 'a%c', false],
            'underscore is not a wildcard' => ['contains', 'abc', 'a_c', false],
            'multibyte' => ['contains', 'café', 'é', true],
        ];
    }

    #[DataProvider('strings')]
    public function testStrings(string $operator, mixed $actual, string $value, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate(FieldType::String, $operator, $actual, $value));
    }

    public static function booleans(): array
    {
        return [
            'true is true' => [true, true, true],
            'int 1 is true' => [1, true, true],
            'string 1 is true' => ['1', true, true],
            'false is false' => [false, false, true],
            'int 0 is false' => [0, false, true],
            'string 0 is false' => ['0', false, true],
            'true is not false' => [true, false, false],
            'string 0 is not true' => ['0', true, false],
            'config value 1' => [true, 1, true],
            'config value string 0' => [false, '0', true],
            'string true is not a boolean' => ['true', true, false],
            'int 2 is not a boolean' => [2, true, false],
            'null is not false' => [null, false, false],
            'empty string is not false' => ['', false, false],
        ];
    }

    #[DataProvider('booleans')]
    public function testBooleans(mixed $actual, mixed $value, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate(FieldType::Boolean, 'is', $actual, $value));
    }

    public static function dates(): array
    {
        $utc = new DateTimeZone('UTC');
        $at = FieldType::DateTime;
        $on = FieldType::Date;
        $noon = '2026-10-08T12:00:00Z';
        $far = '2999-12-31';

        return [
            'before' => [$at, 'before', '2026-10-07T12:00:00Z', $noon, true],
            'after' => [$at, 'after', '2026-10-09T12:00:00Z', $noon, true],
            'equal is not before' => [$at, 'before', $noon, $noon, false],
            'equal is not after' => [$at, 'after', $noon, $noon, false],
            'same instant, other offset' => [$at, 'before', '2026-10-08T20:00:00+08:00', $noon, false],
            'same instant, other offset, after' => [$at, 'after', '2026-10-08T20:00:00+08:00', $noon, false],
            'no offset means UTC' => [$at, 'before', '2026-10-08 11:59:59', $noon, true],
            'database format' => [$at, 'after', '2026-10-08 12:00:01', '2026-10-08T12:00:00+00:00', true],
            'microseconds count' => [$at, 'after', '2026-10-08T12:00:00.000001Z', $noon, true],
            'date-only string on datetime field' => [$at, 'before', '2026-10-08', '2026-10-08T00:00:01Z', true],
            'object actual' => [$at, 'before', new DateTimeImmutable('2026-10-07', $utc), '2026-10-08', true],
            'date field ignores time' => [$on, 'before', '2026-10-08T01:00:00Z', '2026-10-08T23:00:00Z', false],
            'date field earlier day' => [$on, 'before', '2026-10-07T23:59:59Z', '2026-10-08', true],
            'date field uses day as written' => [$on, 'after', '2026-10-08T00:00:00+08:00', '2026-10-07', true],
            'date field, year past 9999' => [
                $on,
                'after',
                (new DateTimeImmutable('9999-12-31', $utc))->modify('+1 day'),
                '9999-12-31',
                true,
            ],
            'impossible date never matches' => [$at, 'before', '2026-02-30', $far, false],
            'relative string never matches' => [$at, 'before', 'yesterday', $far, false],
            'hour 24 never matches' => [$at, 'before', '2026-10-08T24:00:00Z', $far, false],
            'timestamp never matches' => [$at, 'before', 0, $far, false],
            'null never matches' => [$at, 'before', null, $far, false],
            'offset hour 24 never matches' => [$at, 'before', '2026-10-08T12:00:00+24:59', $far, false],
            'offset minute 60 never matches' => [$at, 'before', '2026-10-08T12:00:00+08:60', $far, false],
            'largest offset matches' => [$at, 'before', '2026-10-08T12:00:00-23:59', $far, true],
            'trailing newline never matches' => [$at, 'before', "2026-10-08\n", $far, false],
        ];
    }

    #[DataProvider('dates')]
    public function testDates(FieldType $type, string $operator, mixed $actual, mixed $value, bool $expected): void
    {
        $this->assertSame($expected, $this->evaluate($type, $operator, $actual, $value));
    }

    public function testDateObjectIsNotModified(): void
    {
        $actual = new DateTime('2026-10-08 20:00:00', new DateTimeZone('Asia/Kuala_Lumpur'));

        $this->evaluate(FieldType::Date, 'before', $actual, '2026-10-09');

        $this->assertSame('2026-10-08 20:00:00 Asia/Kuala_Lumpur', $actual->format('Y-m-d H:i:s e'));
    }

    public static function invalidValues(): array
    {
        return [
            'string needs a string' => [FieldType::String, 'equals', 5, 'needs a string [value]'],
            'string rejects null' => [FieldType::String, 'contains', null, 'needs a string [value]'],
            'boolean rejects yes' => [FieldType::Boolean, 'is', 'yes', 'needs a boolean [value]'],
            'boolean rejects string true' => [FieldType::Boolean, 'is', 'true', 'needs a boolean [value]'],
            'boolean rejects null' => [FieldType::Boolean, 'is', null, 'needs a boolean [value]'],
            'date rejects relative string' => [FieldType::DateTime, 'before', 'tomorrow', 'ISO 8601 date [value]'],
            'date rejects impossible date' => [FieldType::Date, 'after', '2026-02-30', 'ISO 8601 date [value]'],
            'date rejects timestamp' => [FieldType::DateTime, 'after', 1760000000, 'ISO 8601 date [value]'],
            'date rejects offset hour 24' => [FieldType::DateTime, 'after', '2026-10-08T12:00+24:59', 'ISO 8601'],
            'date rejects trailing newline' => [FieldType::Date, 'after', "2026-10-08\n", 'ISO 8601 date [value]'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValueThrows(FieldType $type, string $operator, mixed $value, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->evaluate($type, $operator, 'anything', $value);
    }

    private function evaluate(FieldType $type, string $operator, mixed $actual, mixed $value): bool
    {
        return (new Evaluator())->evaluate(
            new ExpressionDefinition("field.{$operator}", 'field', $operator, $type, ''),
            new ConfiguredExpression("field.{$operator}", ['value' => $value]),
            ['field' => $actual],
        );
    }
}
