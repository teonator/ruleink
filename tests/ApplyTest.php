<?php

declare(strict_types=1);

namespace RuleInk\Tests;

use ArrayIterator;
use DateTimeImmutable;
use Generator;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuleInk\ConfiguredExpression;
use RuleInk\DatasourceAdapter;
use RuleInk\ExpressionDefinition;
use RuleInk\FieldType;
use RuleInk\InvalidConfiguredExpression;
use RuleInk\RuleInk;
use RuleInk\ValueDeclaration;
use Traversable;
use UnexpectedValueException;

final class ApplyTest extends TestCase
{
    private int $started = 0;

    private int $read = 0;

    public function testAppliesAGeneratedExpressionToAnArray(): void
    {
        $result = $this->ruleink()->apply(
            'product',
            [10 => ['price' => 50], 20 => ['price' => 150], 30 => ['price' => 300]],
            'price.gt',
            ['value' => 100],
        );

        $this->assertSame([20 => ['price' => 150], 30 => ['price' => 300]], $result);
    }

    public function testAppliesACustomExpression(): void
    {
        $ruleink = $this->ruleink();
        $ruleink->define(
            'product',
            $this->isExpensive(),
            fn (mixed $subject, array $values): bool => $subject['price'] >= ($values['threshold'] ?? 1000),
        );

        $subjects = [['price' => 500], ['price' => 1500]];

        $this->assertSame([1 => ['price' => 1500]], $ruleink->apply('product', $subjects, 'product.is_expensive'));
        $this->assertSame($subjects, $ruleink->apply('product', $subjects, 'product.is_expensive', ['threshold' => 1]));
    }

    public function testTraversableIsCheckedOnlyAsTheCallerLoops(): void
    {
        $calls = 0;
        $ruleink = $this->ruleink();
        $ruleink->define('product', $this->isExpensive(), $this->countingIsExpensive($calls));

        $result = $ruleink->apply(
            'product',
            $this->subjects([['price' => 500], ['price' => 1500], ['price' => 2000]]),
            'product.is_expensive',
        );

        $this->assertInstanceOf(Traversable::class, $result);
        $this->assertSame(0, $this->started);
        $this->assertSame(0, $calls);

        foreach ($result as $key => $subject) {
            break;
        }

        $this->assertSame([1, ['price' => 1500]], [$key ?? null, $subject ?? null]);
        $this->assertSame(2, $this->read);
        $this->assertSame(2, $calls);
    }

    public function testArrayIteratorCanBeLoopedTwice(): void
    {
        $calls = 0;
        $ruleink = $this->ruleink();
        $ruleink->define('product', $this->isExpensive(), $this->countingIsExpensive($calls));

        $result = $ruleink->apply(
            'product',
            new ArrayIterator([['price' => 500], ['price' => 1500]]),
            'product.is_expensive',
        );

        $this->assertSame([1 => ['price' => 1500]], iterator_to_array($result));
        $this->assertSame(2, $calls);
        $this->assertSame([1 => ['price' => 1500]], iterator_to_array($result));
        $this->assertSame(4, $calls);
    }

    public function testAuthorErrorThrowsBeforeALazyDatasourceStarts(): void
    {
        try {
            $this->ruleink()->apply('product', $this->subjects([]), 'price.gt', ['value' => 'lots']);
            $this->fail('Expected InvalidConfiguredExpression.');
        } catch (InvalidConfiguredExpression $exception) {
            $this->assertSame('product', $exception->schema);
            $this->assertSame('value', $exception->errors[0]->value);
        }

        $this->assertSame(0, $this->started);
    }

    public function testUnknownKeyIsAnAuthorError(): void
    {
        $this->expectException(InvalidConfiguredExpression::class);
        $this->expectExceptionMessage('Unknown expression [price.between] in schema [product].');

        $this->ruleink()->apply('product', [], 'price.between', ['value' => 1]);
    }

    public function testUnknownSchemaIsADeveloperError(): void
    {
        try {
            $this->ruleink()->apply('order', $this->subjects([]), 'price.gt', ['value' => 1]);
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotInstanceOf(InvalidConfiguredExpression::class, $exception);
            $this->assertSame('Unknown schema [order].', $exception->getMessage());
        }

        $this->assertSame(0, $this->started);
    }

    public function testUnsupportedDatasourceIsADeveloperError(): void
    {
        try {
            $this->ruleink()->apply('product', 'products', 'price.gt', ['value' => 1]);
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotInstanceOf(InvalidConfiguredExpression::class, $exception);
            $this->assertSame('No datasource adapter supports string.', $exception->getMessage());
        }
    }

    public function testFirstSupportingAdapterWins(): void
    {
        $unsupported = $this->adapter(supports: false);
        $first = $this->adapter(result: 'first');
        $second = $this->adapter(result: 'second');
        $ruleink = new RuleInk(adapters: [$unsupported, $first, $second]);
        $ruleink->register(ExpressionGeneratorTest::product());

        $this->assertSame('first', $ruleink->apply('product', [], 'price.gt', ['value' => 100]));
        $this->assertNull($unsupported->applied);
        $this->assertNull($second->applied);
    }

    public function testAdapterReceivesTheDatasourceExpressionAndAMatchCheck(): void
    {
        $adapter = $this->adapter();
        $ruleink = new RuleInk(adapters: [$adapter]);
        $ruleink->register(ExpressionGeneratorTest::product());
        $datasource = [['price' => 150]];

        $ruleink->apply('product', $datasource, 'price.gt', ['value' => 100]);

        $registered = array_column($ruleink->expressionsFor('product'), null, 'key')['price.gt'];

        $this->assertSame($datasource, $adapter->datasource);
        $this->assertSame($registered, $adapter->checked);
        $this->assertSame($registered, $adapter->definition);
        $this->assertEquals(new ConfiguredExpression('price.gt', ['value' => 100]), $adapter->applied);
        $this->assertSame([true, false], $adapter->verdicts);
    }

    public function testAdapterThatCannotApplyTheExpressionThrows(): void
    {
        $adapter = $this->adapter(canApply: false);
        $ruleink = new RuleInk(adapters: [$adapter]);
        $ruleink->register(ExpressionGeneratorTest::product());

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot apply expression [price.gt].');

        try {
            $ruleink->apply('product', [], 'price.gt', ['value' => 1]);
        } finally {
            $this->assertNull($adapter->applied);
        }
    }

    public function testCustomExpressionReturningANonBoolThrowsWhileLooping(): void
    {
        $ruleink = $this->ruleink();
        $ruleink->define('product', $this->isExpensive(), fn (mixed $subject, array $values): mixed => 1);

        $result = $ruleink->apply('product', $this->subjects([['price' => 1]]), 'product.is_expensive');

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Custom expression [product.is_expensive] must return a bool, got int.');

        iterator_to_array($result);
    }

    /**
     * Applying must keep exactly the subjects that evaluate() matches. Query adapters will reuse this.
     *
     * @param array<string, mixed> $values
     */
    #[DataProvider('parity')]
    public function testApplyAgreesWithEvaluate(string $key, array $values): void
    {
        $ruleink = $this->ruleink();
        $subjects = [
            [],
            ['name' => null, 'price' => null, 'active' => null, 'released_at' => null],
            ['name' => '', 'price' => '', 'active' => '', 'released_at' => ''],
            ['name' => 'Lamp', 'price' => '100', 'active' => '1', 'released_at' => '2026-01-01'],
            ['name' => 'lamp', 'price' => 100.0, 'active' => 1, 'released_at' => '2026-01-01T08:00:00+08:00'],
            ['name' => 'Desk Lamp', 'price' => '99.99', 'active' => false, 'released_at' => '2025-12-31T23:59:59Z'],
            ['name' => 'LAMP', 'price' => 1e2, 'active' => true, 'released_at' => new DateTimeImmutable('2026-06-01')],
            ['name' => 'Lamp ', 'price' => 'abc', 'active' => 'yes', 'released_at' => 'tomorrow'],
        ];

        $expected = array_filter(
            $subjects,
            fn (array $subject): bool => $ruleink->evaluate('product', $subject, $key, $values),
        );

        $lazy = $ruleink->apply('product', new ArrayIterator($subjects), $key, $values);

        $this->assertSame($expected, $ruleink->apply('product', $subjects, $key, $values));
        $this->assertSame($expected, iterator_to_array($lazy));
    }

    /**
     * @return array<string, array{string, array<string, mixed>}>
     */
    public static function parity(): array
    {
        return [
            'name.equals' => ['name.equals', ['value' => 'Lamp']],
            'name.contains' => ['name.contains', ['value' => 'amp']],
            'name.contains empty' => ['name.contains', ['value' => '']],
            'price.eq' => ['price.eq', ['value' => 100]],
            'price.eq string' => ['price.eq', ['value' => '100.00']],
            'price.gt' => ['price.gt', ['value' => 99.99]],
            'price.gte' => ['price.gte', ['value' => '1e2']],
            'price.lt' => ['price.lt', ['value' => 100]],
            'price.lte' => ['price.lte', ['value' => 0]],
            'active.is true' => ['active.is', ['value' => true]],
            'active.is false' => ['active.is', ['value' => '0']],
            'released_at.before' => ['released_at.before', ['value' => '2026-01-01T00:00:00Z']],
            'released_at.after' => ['released_at.after', ['value' => '2025-12-31T23:59:59+00:00']],
        ];
    }

    private function ruleink(): RuleInk
    {
        $ruleink = new RuleInk();
        $ruleink->register(ExpressionGeneratorTest::product());

        return $ruleink;
    }

    private function isExpensive(): ExpressionDefinition
    {
        return ExpressionDefinition::custom(
            'product.is_expensive',
            'Product is expensive',
            [new ValueDeclaration('threshold', FieldType::Number, required: false)],
        );
    }

    /**
     * @return callable(mixed, array<string, mixed>): bool
     */
    private function countingIsExpensive(int &$calls): callable
    {
        return function (mixed $subject, array $values) use (&$calls): bool {
            $calls++;

            return $subject['price'] >= 1000;
        };
    }

    /**
     * A generator that counts whether anything has started looping over it, and how many subjects it gave out.
     *
     * @param list<mixed> $subjects
     */
    private function subjects(array $subjects): Generator
    {
        $this->started++;

        foreach ($subjects as $key => $subject) {
            $this->read++;

            yield $key => $subject;
        }
    }

    /**
     * Records what canApply() and apply() received, and what $matches says for a price of 150 and of 50.
     */
    private function adapter(bool $supports = true, bool $canApply = true, mixed $result = null): DatasourceAdapter
    {
        return new class ($supports, $canApply, $result) implements DatasourceAdapter {
            public ?ExpressionDefinition $checked = null;

            public mixed $datasource = null;

            public ?ExpressionDefinition $definition = null;

            public ?ConfiguredExpression $applied = null;

            /** @var list<bool> */
            public array $verdicts = [];

            public function __construct(private bool $supports, private bool $canApply, private mixed $result)
            {
            }

            public function supports(mixed $datasource): bool
            {
                return $this->supports;
            }

            public function canApply(ExpressionDefinition $definition): bool
            {
                $this->checked = $definition;

                return $this->canApply;
            }

            public function apply(
                mixed $datasource,
                ExpressionDefinition $definition,
                ConfiguredExpression $expression,
                callable $matches,
            ): mixed {
                $this->datasource = $datasource;
                $this->definition = $definition;
                $this->applied = $expression;
                $this->verdicts = [$matches(['price' => 150]), $matches(['price' => 50])];

                return $this->result;
            }
        };
    }
}
