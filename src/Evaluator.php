<?php

declare(strict_types=1);

namespace Ruleink;

use InvalidArgumentException;

final class Evaluator
{
    /** Number operators and the compareNumbers() results each one accepts. */
    private const ORDERINGS = [
        'eq' => [0],
        'gt' => [1],
        'gte' => [0, 1],
        'lt' => [-1],
        'lte' => [-1, 0],
    ];

    public function __construct(
        private ValueResolver $resolver = new DefaultValueResolver(),
    ) {
    }

    public function evaluate(
        ExpressionDefinition $definition,
        ConfiguredExpression $expression,
        mixed $subject,
    ): bool {
        if ($definition->key !== $expression->expression) {
            throw new InvalidArgumentException(
                "Configured expression [{$expression->expression}] does not match definition [{$definition->key}].",
            );
        }

        $this->assertOperatorFitsFieldType($definition);

        return match ($definition->operator) {
            'eq', 'gt', 'gte', 'lt', 'lte' => $this->compareNumber($definition, $expression, $subject),
            default => throw new InvalidArgumentException("Unsupported operator [{$definition->operator}]."),
        };
    }

    private function compareNumber(
        ExpressionDefinition $definition,
        ConfiguredExpression $expression,
        mixed $subject,
    ): bool {
        $expected = $this->numericValue($expression);
        $actual = $this->resolver->get($subject, $definition->field);

        // Checked first because PHP 8 compares a non-numeric string with a number as strings: 'abc' > 100.
        if (!is_numeric($actual)) {
            return false;
        }

        // compareNumbers() returns null for NAN, which no operator accepts.
        return in_array($this->compareNumbers($actual, $expected), self::ORDERINGS[$definition->operator], true);
    }

    private function assertOperatorFitsFieldType(ExpressionDefinition $definition): void
    {
        if (!in_array($definition->operator, $definition->fieldType->operators(), true)) {
            throw new InvalidArgumentException(
                "Operator [{$definition->operator}] does not support [{$definition->fieldType->value}] fields.",
            );
        }
    }

    private function numericValue(ConfiguredExpression $expression): int|float|string
    {
        $value = $expression->values['value'] ?? null;

        if (!is_numeric($value) || (is_float($value) && !is_finite($value))) {
            throw new InvalidArgumentException(
                "Expression [{$expression->expression}] needs a finite numeric [value].",
            );
        }

        return $value;
    }

    /**
     * Compares exactly as decimals, so no value loses precision to float conversion.
     * Returns null when either side is NAN.
     */
    private function compareNumbers(int|float|string $a, int|float|string $b): ?int
    {
        $x = $this->decimal($a);
        $y = $this->decimal($b);

        if ($x === null || $y === null) {
            return null;
        }

        [$signX, $exponentX, $digitsX] = $x;
        [$signY, $exponentY, $digitsY] = $y;

        if ($signX !== $signY || $signX === 0) {
            return $signX <=> $signY;
        }

        if ($exponentX === null || $exponentY === null) {
            return $signX * (($exponentX === null) <=> ($exponentY === null));
        }

        $magnitude = $this->compareIntegers($exponentX, $exponentY) ?: strcmp($digitsX, $digitsY) <=> 0;

        return $signX * $magnitude;
    }

    /**
     * Normalises a number to sign × 0.digits × 10^exponent. INF has a null exponent.
     * Floats use their shortest round-trip decimal, so 0.1 equals '0.1'.
     *
     * @return array{int, ?string, string}|null
     */
    private function decimal(int|float|string $number): ?array
    {
        if (is_float($number)) {
            if (is_nan($number)) {
                return null;
            }

            if (is_infinite($number)) {
                return [$number > 0 ? 1 : -1, null, ''];
            }

            $number = $this->shortestDecimal($number);
        }

        if (!preg_match('/^\s*([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?\s*$/', (string) $number, $matches)) {
            return null;
        }

        $mantissa = $matches[2] . ($matches[3] ?? '');
        $digits = ltrim($mantissa, '0');

        if ($digits === '') {
            return [0, '0', ''];
        }

        $offset = strlen($matches[2]) - (strlen($mantissa) - strlen($digits));
        $exponent = $this->addToInteger($matches[4] ?? '0', $offset);

        return [$matches[1] === '-' ? -1 : 1, $exponent, rtrim($digits, '0')];
    }

    /**
     * Exponents stay digit strings because '1e99999999999999999999' overflows a PHP int.
     */
    private function addToInteger(string $integer, int $offset): string
    {
        $negative = $integer[0] === '-';
        $magnitude = ltrim($integer, '+-0');

        if (strlen($magnitude) <= 18) {
            return (string) (($negative ? -1 : 1) * (int) $magnitude + $offset);
        }

        // Past 18 digits |integer| > |offset|, so the sign cannot flip.
        return ($negative ? '-' : '') . $this->addToMagnitude($magnitude, $negative ? -$offset : $offset);
    }

    private function addToMagnitude(string $magnitude, int $delta): string
    {
        if (strlen($magnitude) <= 18) {
            return (string) ((int) $magnitude + $delta);
        }

        $low = (int) substr($magnitude, -18) + $delta;
        $carry = $low < 0 ? -1 : ($low >= 10 ** 18 ? 1 : 0);
        $low -= $carry * 10 ** 18;
        $high = $this->addToMagnitude(substr($magnitude, 0, -18), $carry);

        return ltrim($high . str_pad((string) $low, 18, '0', STR_PAD_LEFT), '0');
    }

    private function compareIntegers(string $a, string $b): int
    {
        $signA = $a[0] === '-' ? -1 : ($a === '0' ? 0 : 1);
        $signB = $b[0] === '-' ? -1 : ($b === '0' ? 0 : 1);

        if ($signA !== $signB) {
            return $signA <=> $signB;
        }

        $a = ltrim($a, '-');
        $b = ltrim($b, '-');

        return $signA * (strlen($a) <=> strlen($b) ?: strcmp($a, $b) <=> 0);
    }

    private function shortestDecimal(float $number): string
    {
        // %h is %g without the locale's decimal comma.
        for ($precision = 1; $precision < 17; $precision++) {
            $decimal = sprintf("%.{$precision}h", $number);

            if ((float) $decimal === $number) {
                return $decimal;
            }
        }

        return sprintf('%.17h', $number);
    }
}
