<?php

declare(strict_types=1);

namespace Ruleink;

use InvalidArgumentException;
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
     * Custom evaluators must have no side effects and give the same answer for the same input,
     * because applying to a datasource may call them many times.
     *
     * @param callable(mixed $subject, array<string, mixed> $values): bool $evaluator
     */
    public function define(string $schema, ExpressionDefinition $definition, callable $evaluator): void
    {
        if ($definition->operator !== null) {
            throw new InvalidArgumentException(
                "Expression [{$definition->key}] is generated; define() only takes custom expressions.",
            );
        }

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
     * Lists every author error in a configured expression, or nothing when it is valid.
     *
     * @param array<array-key, mixed> $values
     * @return list<AuthorError>
     */
    public function validate(string $schema, string $key, array $values = []): array
    {
        return $this->check($schema, $key, $values)[1];
    }

    /**
     * @param array<array-key, mixed> $values
     * @throws InvalidConfiguredExpression for author errors; developer errors throw plain exceptions
     */
    public function evaluate(string $schema, mixed $subject, string $key, array $values = []): bool
    {
        [$definition, $errors] = $this->check($schema, $key, $values);

        if ($definition === null || $errors !== []) {
            throw new InvalidConfiguredExpression($key, $errors, $schema);
        }

        $custom = $this->customEvaluators[$schema][$key] ?? null;

        if ($custom === null) {
            return $this->evaluator->evaluate($definition, new ConfiguredExpression($key, $values), $subject);
        }

        $result = $custom($subject, $values);

        if (!is_bool($result)) {
            throw new UnexpectedValueException(
                "Custom expression [{$key}] must return a bool, got " . get_debug_type($result) . '.',
            );
        }

        return $result;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array{?ExpressionDefinition, list<AuthorError>}
     */
    private function check(string $schema, string $key, array $values): array
    {
        $definition = $this->registry->find($schema, $key);

        if ($definition === null) {
            return [null, [new AuthorError("Unknown expression [{$key}] in schema [{$schema}].")]];
        }

        return [$definition, $this->evaluator->validate($definition, $values)];
    }
}
