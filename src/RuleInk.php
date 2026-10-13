<?php

declare(strict_types=1);

namespace RuleInk;

use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

final class RuleInk
{
    /** @var array<string, array<string, callable(mixed, array<string, mixed>): bool>> schema => key => evaluator */
    private array $customEvaluators = [];

    /** @var list<DatasourceAdapter> */
    private array $adapters;

    /**
     * @param list<DatasourceAdapter> $adapters checked in order before the built-in iterable adapter;
     *                                          the first that supports a datasource applies to it
     */
    public function __construct(
        private ExpressionRegistry $registry = new ExpressionRegistry(),
        private Evaluator $evaluator = new Evaluator(),
        array $adapters = [],
    ) {
        $this->adapters = [...$adapters, new IterableDatasourceAdapter()];
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
        $definition = $this->definitionFor($schema, $key, $values);

        return $this->matches($schema, $definition, new ConfiguredExpression($key, $values), $subject);
    }

    /**
     * Narrows a datasource to the subjects that match. The configured expression is checked now,
     * even when the result is lazy; problems in individual subjects are just no match.
     *
     * @param array<array-key, mixed> $values
     * @return mixed whatever the adapter returns: a narrowed array, a lazy iterable, a constrained query
     * @throws InvalidConfiguredExpression for author errors; developer errors throw plain exceptions
     */
    public function apply(string $schema, mixed $datasource, string $key, array $values = []): mixed
    {
        $definition = $this->definitionFor($schema, $key, $values);

        $adapter = $this->adapterFor($datasource);

        if (!$adapter->canApply($definition)) {
            throw new LogicException(
                'Datasource adapter [' . $adapter::class . "] cannot apply expression [{$key}].",
            );
        }

        $expression = new ConfiguredExpression($key, $values);

        return $adapter->apply(
            $datasource,
            $definition,
            $expression,
            fn (mixed $subject): bool => $this->matches($schema, $definition, $expression, $subject),
        );
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

    /**
     * @param array<array-key, mixed> $values
     */
    private function definitionFor(string $schema, string $key, array $values): ExpressionDefinition
    {
        [$definition, $errors] = $this->check($schema, $key, $values);

        if ($definition === null || $errors !== []) {
            throw new InvalidConfiguredExpression($key, $errors, $schema);
        }

        return $definition;
    }

    private function adapterFor(mixed $datasource): DatasourceAdapter
    {
        foreach ($this->adapters as $adapter) {
            if ($adapter->supports($datasource)) {
                return $adapter;
            }
        }

        throw new InvalidArgumentException(
            'No datasource adapter supports ' . get_debug_type($datasource) . '.',
        );
    }

    private function matches(
        string $schema,
        ExpressionDefinition $definition,
        ConfiguredExpression $expression,
        mixed $subject,
    ): bool {
        $custom = $this->customEvaluators[$schema][$expression->key] ?? null;

        if ($custom === null) {
            return $this->evaluator->evaluate($definition, $expression, $subject);
        }

        $result = $custom($subject, $expression->values);

        if (!is_bool($result)) {
            throw new UnexpectedValueException(
                "Custom expression [{$expression->key}] must return a bool, got " . get_debug_type($result) . '.',
            );
        }

        return $result;
    }
}
