<?php

declare(strict_types=1);

namespace RuleInk;

/**
 * Applies configured expressions to one kind of datasource. In-memory adapters call $matches; query adapters
 * ignore it, translate the definition instead, and refuse what they cannot translate through canApply() or by
 * throwing ApplyRefused.
 */
interface DatasourceAdapter
{
    public function supports(mixed $datasource): bool;

    /**
     * False makes RuleInk::apply() throw ApplyRefused for this datasource; RuleInk does not try the next adapter.
     */
    public function canApply(ExpressionDefinition $definition): bool;

    /**
     * Narrows the datasource to matching subjects without forcing a query to run.
     *
     * @param callable(mixed $subject): bool $matches evaluates one subject; query adapters may ignore it
     * @throws ApplyRefused when it cannot keep exactly the subjects $matches would keep; this may depend on the values
     */
    public function apply(
        mixed $datasource,
        ExpressionDefinition $definition,
        ConfiguredExpression $expression,
        callable $matches,
    ): mixed;
}
