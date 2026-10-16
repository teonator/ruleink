<?php

declare(strict_types=1);

namespace RuleInk;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;

/**
 * How expressions read booleans, dates and numbers. The Evaluator and database adapters share it, so a query reads
 * a value the same way evaluate() does.
 */
final class ValueReader
{
    private DateTimeZone $timezone;

    /**
     * @param ?DateTimeZone $timezone for datetime strings without an offset; UTC when not given
     */
    public function __construct(?DateTimeZone $timezone = null)
    {
        $this->timezone = $timezone ?? new DateTimeZone('UTC');
    }

    /**
     * The timezone of datetime strings that have no offset.
     */
    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * Writes a number as a plain decimal, such as 1e2 as 100, so a database can compare it without rounding.
     * Floats use their shortest round-trip decimal, so 0.1 is 0.1. Null for INF, NAN, anything not a number, and
     * numbers longer than 1000 characters written out.
     */
    public function decimal(int|float|string $number): ?string
    {
        if (is_float($number)) {
            if (!is_finite($number)) {
                return null;
            }

            $number = $this->shortestDecimal($number);
        }

        $pattern = '/^\s*([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?\s*$/';

        if (!preg_match($pattern, (string) $number, $matches) || $matches[2] . ($matches[3] ?? '') === '') {
            return null;
        }

        // An exponent past four digits cannot fit in 1000 characters.
        if (strlen(ltrim($matches[4] ?? '', '+-0')) > 4) {
            return null;
        }

        $digits = $matches[2] . ($matches[3] ?? '');
        $point = strlen($matches[2]) + (int) ($matches[4] ?? 0);

        if ($point < 0) {
            $digits = str_repeat('0', -$point) . $digits;
            $point = 0;
        }

        $digits = str_pad($digits, $point, '0');
        $integer = ltrim(substr($digits, 0, $point), '0') ?: '0';
        $fraction = rtrim(substr($digits, $point), '0');
        $plain = $fraction === '' ? $integer : "{$integer}.{$fraction}";

        if (strlen($plain) > 1000) {
            return null;
        }

        return $matches[1] === '-' && $plain !== '0' ? "-{$plain}" : $plain;
    }

    /**
     * Databases often return booleans as 1/0 or '1'/'0'; anything else is not a boolean.
     */
    public function boolean(mixed $value): ?bool
    {
        return match (true) {
            in_array($value, [true, 1, '1'], true) => true,
            in_array($value, [false, 0, '0'], true) => false,
            default => null,
        };
    }

    /**
     * Accepts a DateTimeInterface or a strict ISO 8601 string; strings without an offset are in this reader's
     * timezone. Relative strings like 'tomorrow' and impossible dates like 2026-02-30 are rejected.
     */
    public function dateTime(mixed $value): ?DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        // \z, not $: $ also matches before a trailing newline.
        $pattern = '/^(\d{4})-(\d{2})-(\d{2})'
            . '(?:[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,6})?)?(?:Z|[+-](\d{2}):?(\d{2}))?)?\z/';

        if (!is_string($value) || !preg_match($pattern, $value, $matches)) {
            return null;
        }

        // RFC 3339 bounds: hours and offset hours 0-23, minutes and seconds 0-59.
        $valid = checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])
            && (int) ($matches[4] ?? 0) < 24
            && (int) ($matches[5] ?? 0) < 60
            && (int) ($matches[6] ?? 0) < 60
            && (int) ($matches[7] ?? 0) < 24
            && (int) ($matches[8] ?? 0) < 60;

        try {
            return $valid ? new DateTimeImmutable($value, $this->timezone) : null;
        } catch (Exception) {
            return null;
        }
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
