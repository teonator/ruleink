<?php

declare(strict_types=1);

namespace RuleInk;

/**
 * Applies configured expressions to one kind of datasource. In-memory adapters call $matches; query adapters
 * ignore it, translate the definition instead, and use canApply() to reject what they cannot translate.
 */
interface DatasourceAdapter
{
    public function supports(mixed $datasource): bool;

    /**
     * False makes RuleInk::apply() throw for this datasource; RuleInk does not try the next adapter.
     */
    public function canApply(ExpressionDefinition $definition): bool;

    /**
     * Narrows the datasource to matching subjects without forcing a query to run.
     *
     * @param callable(mixed $subject): bool $matches evaluates one subject; query adapters may ignore it
     */
    public function apply(
        mixed $datasource,
        ExpressionDefinition $definition,
        ConfiguredExpression $expression,
        callable $matches,
    ): mixed;
}
