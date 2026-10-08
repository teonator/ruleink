<?php

declare(strict_types=1);

namespace Ruleink;

final readonly class ConfiguredExpression
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        public string $expression,
        public array $values = [],
    ) {
    }
}
