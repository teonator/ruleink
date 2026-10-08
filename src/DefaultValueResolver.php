<?php

declare(strict_types=1);

namespace Ruleink;

final class DefaultValueResolver implements ValueResolver
{
    /**
     * Reads a top-level array key or accessible object property; any other subject resolves to null.
     */
    public function get(mixed $subject, string $path): mixed
    {
        if (is_array($subject)) {
            return $subject[$path] ?? null;
        }

        if (is_object($subject)) {
            return $subject->{$path} ?? null;
        }

        return null;
    }
}
