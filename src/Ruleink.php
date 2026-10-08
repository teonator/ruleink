<?php

declare(strict_types=1);

namespace Ruleink;

use UnexpectedValueException;

final class Ruleink
{
    /** @var array<string, array<string, callable(mixed, array<string, mixed>): bool>> schema => key => evaluator */
    private array $customEvaluators = [];

    public function __construct(
        private ExpressionRegistry $registry = new ExpressionRegistry(),
        private Evaluator $evaluator = new Evaluator(),
    ) {
    }

    public function register(Schema $schema): void
    {
        $this->registry->register($schema);
    }

    /**
     * @param callable(mixed $subject, array<string, mixed> $values): bool $evaluator
     */
    public function define(string $schema, ExpressionDefinition $definition, callable $evaluator): void
    {
        $this->registry->add($schema, $definition);
        $this->customEvaluators[$schema][$definition->key] = $evaluator;
    }

    /**
     * @return list<ExpressionDefinition>
     */
    public function expressionsFor(string $schema): array
    {
        return $this->registry->expressionsFor($schema);
    }

    /**
     * @param array<string, mixed> $values
     */
    public function evaluate(string $schema, mixed $subject, string $expression, array $values = []): bool
    {
        $definition = $this->registry->definition($schema, $expression);
        $custom = $this->customEvaluators[$schema][$expression] ?? null;

        if ($custom === null) {
            return $this->evaluator->evaluate($definition, new ConfiguredExpression($expression, $values), $subject);
        }

        $result = $custom($subject, $values);

        if (!is_bool($result)) {
            throw new UnexpectedValueException(
                "Custom expression [{$expression}] must return a bool, got " . get_debug_type($result) . '.',
            );
        }

        return $result;
    }
}
