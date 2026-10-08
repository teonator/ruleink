<?php

declare(strict_types=1);

namespace Ruleink;

final class ValueResolver
{
    /**
     * Reads a top-level array key or accessible object property.
     * A missing key/property resolves to null, same as an explicit null.
     */
    public function resolve(array|object $subject, string $field): mixed
    {
        if (is_array($subject)) {
            return $subject[$field] ?? null;
        }

        return $subject->{$field} ?? null;
    }
}
