<?php

declare(strict_types=1);

namespace Ruleink;

use InvalidArgumentException;

final class ExpressionRegistry
{
    /** @var array<string, list<ExpressionDefinition>> keyed by schema name */
    private array $definitions = [];

    public function __construct(
        private ExpressionGenerator $generator = new ExpressionGenerator(),
    ) {
    }

    public function register(Schema $schema): void
    {
        if (isset($this->definitions[$schema->name])) {
            throw new InvalidArgumentException("Duplicate schema [{$schema->name}].");
        }

        $this->definitions[$schema->name] = $this->generator->generate($schema);
    }

    /**
     * @return list<ExpressionDefinition>
     */
    public function expressionsFor(string $schema): array
    {
        return $this->definitions[$schema]
            ?? throw new InvalidArgumentException("Unknown schema [{$schema}].");
    }
}
