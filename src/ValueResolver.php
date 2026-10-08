<?php

declare(strict_types=1);

namespace Ruleink;

interface ValueResolver
{
    /**
     * A missing value resolves to null, same as an explicit null.
     */
    public function get(mixed $subject, string $path): mixed;
}
