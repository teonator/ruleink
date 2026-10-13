<?php

declare(strict_types=1);

namespace RuleInk\Tests;

use ArrayIterator;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use PHPUnit\Framework\TestCase;
use RuleInk\ConfiguredExpression;
use RuleInk\ExpressionDefinition;
use RuleInk\Field;
use RuleInk\FieldType;
use RuleInk\IterableDatasourceAdapter;
use Traversable;

final class IterableDatasourceAdapterTest extends TestCase
{
    public function testSupportsArraysAndTraversables(): void
    {
        $adapter = new IterableDatasourceAdapter();

        $this->assertTrue($adapter->supports([]));
        $this->assertTrue($adapter->supports(new ArrayIterator([])));
        $this->assertTrue($adapter->supports($this->generate([])));
        $this->assertTrue($adapter->supports($this->aggregate([])));
        $this->assertFalse($adapter->supports('product'));
        $this->assertFalse($adapter->supports(null));
        $this->assertFalse($adapter->supports(new \stdClass()));
    }

    public function testCanApplyEveryExpression(): void
    {
        $adapter = new IterableDatasourceAdapter();

        $this->assertTrue($adapter->canApply($this->definition()));
        $this->assertTrue($adapter->canApply(ExpressionDefinition::custom('product.is_new', 'Product is new')));
    }

    public function testNarrowsAnArrayKeepingKeys(): void
    {
        $result = $this->apply(['a' => 50, 'b' => 150, 7 => 300]);

        $this->assertSame(['b' => 150, 7 => 300], $result);
    }

    public function testTraversableComesBackLazyAndCanBeLoopedAgain(): void
    {
        $calls = 0;
        $result = (new IterableDatasourceAdapter())->apply(
            new ArrayIterator(['a' => 50, 'b' => 150]),
            $this->definition(),
            new ConfiguredExpression('price.gt', ['value' => 100]),
            function (mixed $subject) use (&$calls): bool {
                $calls++;

                return $subject > 100;
            },
        );

        $this->assertInstanceOf(IteratorAggregate::class, $result);
        $this->assertSame(0, $calls);
        $this->assertSame(['b' => 150], iterator_to_array($result));
        $this->assertSame(['b' => 150], iterator_to_array($result));
        $this->assertSame(4, $calls);
    }

    public function testKeepsDuplicateKeysFromAGenerator(): void
    {
        $pairs = [];

        foreach ($this->apply($this->generate([['x', 150], ['x', 50], ['x', 300]])) as $key => $subject) {
            $pairs[] = [$key, $subject];
        }

        $this->assertSame([['x', 150], ['x', 300]], $pairs);
    }

    public function testNarrowsAnIteratorAggregate(): void
    {
        $this->assertSame([1 => 150], iterator_to_array($this->apply($this->aggregate([50, 150]))));
    }

    public function testRejectsANonIterableDatasource(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Datasource must be iterable, got string.');

        $this->apply('product');
    }

    private function apply(mixed $datasource): mixed
    {
        return (new IterableDatasourceAdapter())->apply(
            $datasource,
            $this->definition(),
            new ConfiguredExpression('price.gt', ['value' => 100]),
            fn (mixed $subject): bool => $subject > 100,
        );
    }

    private function definition(): ExpressionDefinition
    {
        return ExpressionDefinition::generated(new Field('price', FieldType::Number), 'gt', 'Price is greater than');
    }

    /**
     * @param list<array{mixed, mixed}> $pairs
     */
    private function generate(array $pairs): Generator
    {
        foreach ($pairs as [$key, $subject]) {
            yield $key => $subject;
        }
    }

    /**
     * @param array<array-key, mixed> $subjects
     * @return IteratorAggregate<array-key, mixed>
     */
    private function aggregate(array $subjects): IteratorAggregate
    {
        return new class ($subjects) implements IteratorAggregate {
            /**
             * @param array<array-key, mixed> $subjects
             */
            public function __construct(private array $subjects)
            {
            }

            public function getIterator(): Traversable
            {
                return new ArrayIterator($this->subjects);
            }
        };
    }
}
