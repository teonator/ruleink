<?php

declare(strict_types=1);

namespace Ruleink;

use InvalidArgumentException;

final class InvalidConfiguredExpression extends InvalidArgumentException
{
    /**
     * @param list<AuthorError> $errors
     */
    public function __construct(
        public readonly string $key,
        public readonly array $errors,
        public readonly ?string $schema = null,
    ) {
        parent::__construct(
            "Invalid configured expression [{$key}]: " . implode(' ', array_column($errors, 'message')),
        );
    }
}
