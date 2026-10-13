<?php

declare(strict_types=1);

namespace RuleInk;

use Closure;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * Applies any expression to arrays and Traversables by evaluating each subject.
 */
final class IterableDatasourceAdapter implements DatasourceAdapter
{
    public function supports(mixed $datasource): bool
    {
        return is_iterable($datasource);
    }

    public function canApply(ExpressionDefinition $definition): bool
    {
        return true;
    }

    /**
     * Arrays are narrowed now. Traversables come back lazy: each loop goes over the datasource again and checks
     * each subject as the caller reaches it, so the result can be looped again only if the datasource can.
     * Both keep the datasource's keys and order.
     *
     * @return array<array-key, mixed>|IteratorAggregate<mixed, mixed>
     */
    public function apply(
        mixed $datasource,
        ExpressionDefinition $definition,
        ConfiguredExpression $expression,
        callable $matches,
    ): array|IteratorAggregate {
        if (is_array($datasource)) {
            return array_filter($datasource, $matches);
        }

        if (!$datasource instanceof Traversable) {
            throw new InvalidArgumentException('Datasource must be iterable, got ' . get_debug_type($datasource) . '.');
        }

        return new class ($datasource, $matches(...)) implements IteratorAggregate {
            /**
             * @param Traversable<mixed, mixed> $datasource
             */
            public function __construct(
                private Traversable $datasource,
                private Closure $matches,
            ) {
            }

            public function getIterator(): Generator
            {
                foreach ($this->datasource as $key => $subject) {
                    if (($this->matches)($subject)) {
                        yield $key => $subject;
                    }
                }
            }
        };
    }
}
