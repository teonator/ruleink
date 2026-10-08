<?php

declare(strict_types=1);

namespace Ruleink;

use InvalidArgumentException;

final class ExpressionRegistry
{
    /** @var array<string, array<string, ExpressionDefinition>> schema name => key => definition */
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

        $this->definitions[$schema->name] = [];

        foreach ($this->generator->generate($schema) as $definition) {
            $this->definitions[$schema->name][$definition->key] = $definition;
        }
    }

    /**
     * Adds a definition, such as a custom expression, to an already registered schema.
     */
    public function add(string $schema, ExpressionDefinition $definition): void
    {
        $this->assertRegistered($schema);

        if (isset($this->definitions[$schema][$definition->key])) {
            throw new InvalidArgumentException("Duplicate expression [{$definition->key}] in schema [{$schema}].");
        }

        $this->definitions[$schema][$definition->key] = $definition;
    }

    public function definition(string $schema, string $key): ExpressionDefinition
    {
        $this->assertRegistered($schema);

        return $this->definitions[$schema][$key]
            ?? throw new InvalidArgumentException("Unknown expression [{$key}] in schema [{$schema}].");
    }

    /**
     * @return list<ExpressionDefinition> generated first, then added ones in the order they were added
     */
    public function expressionsFor(string $schema): array
    {
        $this->assertRegistered($schema);

        return array_values($this->definitions[$schema]);
    }

    private function assertRegistered(string $schema): void
    {
        if (!isset($this->definitions[$schema])) {
            throw new InvalidArgumentException("Unknown schema [{$schema}].");
        }
    }
}
