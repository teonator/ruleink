<?php

declare(strict_types=1);

namespace RuleInk;

use RuntimeException;

/**
 * A datasource adapter refused to apply a configured expression because it cannot keep exactly the subjects
 * evaluate() would match. Not an author error: the refusal may depend on the values, but they fit the expression.
 */
final class ApplyRefused extends RuntimeException
{
    /**
     * @param class-string<DatasourceAdapter> $adapter
     */
    public function __construct(
        public readonly string $key,
        public readonly string $adapter,
        string $message,
    ) {
        parent::__construct($message);
    }
}
