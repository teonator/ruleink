<?php

declare(strict_types=1);

namespace Ruleink;

final class Ruleink
{
    public function __construct(
        private ExpressionRegistry $registry = new ExpressionRegistry(),
    ) {
    }

    public function register(Schema $schema): void
    {
        $this->registry->register($schema);
    }

    /**
     * @return list<ExpressionDefinition>
     */
    public function expressionsFor(string $schema): array
    {
        return $this->registry->expressionsFor($schema);
    }
}
