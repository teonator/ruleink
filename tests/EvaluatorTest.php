<?php

declare(strict_types=1);

namespace Ruleink\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ruleink\ConfiguredExpression;
use Ruleink\Evaluator;
use Ruleink\ExpressionDefinition;
use Ruleink\Field;
use Ruleink\FieldType;
use Ruleink\InvalidConfiguredExpression;
use Ruleink\ValueResolver;

final class EvaluatorTest extends TestCase
{
    private ExpressionDefinition $priceGt;

    protected function setUp(): void
    {
        $this->priceGt = ExpressionDefinition::generated(
            new Field('price', FieldType::Number),
            'gt',
            'Price is greater than',
        );
    }

    public function testPriceOf150IsGreaterThan100(): void
    {
        $this->assertTrue($this->priceGreaterThan(['price' => 150], 100));
    }

    public static function comparisons(): array
    {
        return [
            'greater' => [150, 100, true],
            'equal' => [100, 100, false],
            'less' => [50, 100, false],
            'negative' => [-5, -10, true],
            'numeric string' => ['150', 100, true],
            'numeric string with whitespace' => [' 150', 100, true],
            'decimal' => [100.5, 100, true],
            'decimal threshold string' => [101, '100.5', true],
            'below decimal threshold string' => [100, '100.5', false],
            'trailing zeros are equal' => ['0.1', '0.10', false],
            'negative zero is zero' => ['-0.0', 0, false],
            'exponent string' => ['1e3', 100, true],
            'int beyond 2^53' => [9007199254740993, '9007199254740992.0', true],
            'numeric string beyond 2^53' => ['9007199254740993', 9007199254740992, true],
            'numeric string beyond int64' => ['123456789012345678901234567890', '123456789012345678901234567889', true],
            'threshold beyond float range' => ['1' . str_repeat('0', 310), '1' . str_repeat('0', 309), true],
            'leading dot' => ['.5', '0.4', true],
            'trailing dot' => ['5.', 4, true],
            'explicit plus' => ['+1', 0, true],
            'leading zeros' => ['007', 6, true],
            'trailing whitespace' => ['150 ', 100, true],
            'negative fractions of unequal scale' => ['-1.25', '-1.5', true],
            'more negative fraction' => ['-1.5', '-1.25', false],
            'zero above negative' => ['-0', '-0.1', true],
            'negative below zero' => ['-0.1', 0, false],
            'float actual, string threshold' => [100.5, '100.25', true],
            'string actual, float threshold' => ['100.25', 100.5, false],
            'infinite actual' => [INF, 100, true],
            'nan actual' => [NAN, 100, false],
            'negative infinite actual' => [-INF, -100, false],
            'infinite actual vs decimal beyond float range' => [INF, '1' . str_repeat('0', 309), true],
            'infinite actual vs exponent beyond float range' => [INF, '1e9999', true],
            'exponent beyond float range vs decimal' => ['1e400', '1' . str_repeat('0', 309), true],
            'exponent below float range' => ['1e-400', 0, true],
            'negative exponent' => ['1.5e-3', '0.0014', true],
            'float equals its decimal string' => [0.1, '0.1', false],
            'decimal string equals float' => ['0.1', 0.1, false],
            'float sum above decimal' => [0.1 + 0.2, '0.3', true],
            'huge exponent above' => ['1e99999999999999999999', '1e99999999999999999998', true],
            'huge exponent below' => ['1e99999999999999999998', '1e99999999999999999999', false],
            'huge negative exponent above' => ['1e-99999999999999999998', '1e-99999999999999999999', true],
            'huge negative exponent below' => ['1e-99999999999999999999', '1e-99999999999999999998', false],
            'huge exponent, same value' => ['0.01e-99999999999999999998', '1e-100000000000000000000', false],
            'carry across 18 digits' => ['10e9999999999999999999', '1e10000000000000000000', false],
            'carry across 18 digits, larger' => ['11e9999999999999999999', '1e10000000000000000000', true],
            'borrow across 18 digits' => ['0.001e10000000000000000000', '1e9999999999999999997', false],
            'borrow across 18 digits, larger' => ['0.002e10000000000000000000', '1e9999999999999999997', true],
            'infinite actual vs huge exponent' => [INF, '1e99999999999999999999', true],
            'null' => [null, 100, false],
            'empty string' => ['', 100, false],
            'non-numeric string' => ['abc', 100, false],
            'true' => [true, 0, false],
            'false' => [false, -1, false],
        ];
    }

    #[DataProvider('comparisons')]
    public function testGreaterThan(mixed $actual, int|float|string $threshold, bool $expected): void
    {
        $this->assertSame($expected, $this->priceGreaterThan(['price' => $actual], $threshold));
    }

    public function testMissingFieldIsFalse(): void
    {
        $this->assertFalse($this->priceGreaterThan(['title' => 'x'], 100));
    }

    public function testObjectSubject(): void
    {
        $subject = new \stdClass();
        $subject->price = 150;

        $this->assertTrue($this->priceGreaterThan($subject, 100));
    }

    public function testScalarSubjectIsFalse(): void
    {
        $this->assertFalse($this->priceGreaterThan(150, 100));
    }

    public function testUsesInjectedResolver(): void
    {
        $resolver = new class implements ValueResolver {
            public function get(mixed $subject, string $path): mixed
            {
                return $subject->attributes[$path] ?? null;
            }
        };
        $subject = new \stdClass();
        $subject->attributes = ['price' => 150];

        $result = (new Evaluator($resolver))->evaluate(
            $this->priceGt,
            new ConfiguredExpression('price.gt', ['value' => 100]),
            $subject,
        );

        $this->assertTrue($result);
    }

    public function testInvalidConfigurationThrowsBeforeReadingTheSubject(): void
    {
        $resolver = new class implements ValueResolver {
            public function get(mixed $subject, string $path): mixed
            {
                throw new \LogicException('Resolver should not be called.');
            }
        };

        $this->expectException(InvalidConfiguredExpression::class);

        (new Evaluator($resolver))->evaluate($this->priceGt, new ConfiguredExpression('price.gt'), ['price' => 150]);
    }

    public static function invalidThresholds(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'non-numeric string' => ['abc'],
            'true' => [true],
            'array' => [[100]],
            'nan' => [NAN],
            'infinity' => [INF],
            'negative infinity' => [-INF],
        ];
    }

    #[DataProvider('invalidThresholds')]
    public function testInvalidThresholdThrows(mixed $threshold): void
    {
        $this->expectException(InvalidConfiguredExpression::class);
        $this->expectExceptionMessage('[price.gt]: Value [value] must be a finite number.');

        $this->priceGreaterThan(['price' => 150], $threshold);
    }

    public function testMissingThresholdThrows(): void
    {
        $this->expectException(InvalidConfiguredExpression::class);
        $this->expectExceptionMessage('Invalid configured expression [price.gt]: Missing value [value].');

        (new Evaluator())->evaluate($this->priceGt, new ConfiguredExpression('price.gt'), ['price' => 150]);
    }

    public function testNonNumberFieldThrows(): void
    {
        $definition = ExpressionDefinition::generated(new Field('name', FieldType::String), 'gt', '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator [gt] does not support [string] fields.');

        (new Evaluator())->evaluate(
            $definition,
            new ConfiguredExpression('name.gt', ['value' => 100]),
            ['name' => 'abc'],
        );
    }

    public function testMismatchedExpressionKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Configured expression [price.lt] does not match definition [price.gt].');

        (new Evaluator())->evaluate(
            $this->priceGt,
            new ConfiguredExpression('price.lt', ['value' => 100]),
            ['price' => 150],
        );
    }

    public function testExpressionWithoutAFieldThrows(): void
    {
        $definition = ExpressionDefinition::custom('product.is_expensive', '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Expression [product.is_expensive] is not generated, so only its custom evaluator can evaluate it.',
        );

        (new Evaluator())->evaluate($definition, new ConfiguredExpression('product.is_expensive'), ['price' => 1]);
    }

    public function testOperatorOutsideTheCatalogueThrows(): void
    {
        $definition = ExpressionDefinition::generated(new Field('price', FieldType::Number), 'between', '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator [between] does not support [number] fields.');

        (new Evaluator())->evaluate(
            $definition,
            new ConfiguredExpression('price.between', ['value' => 100]),
            ['price' => 150],
        );
    }

    public static function numberOperators(): array
    {
        return [
            'eq above' => ['eq', 150, false],
            'eq equal' => ['eq', '100.0', true],
            'eq below' => ['eq', 50, false],
            'gte above' => ['gte', 150, true],
            'gte equal' => ['gte', 100, true],
            'gte below' => ['gte', 50, false],
            'lt above' => ['lt', 150, false],
            'lt equal' => ['lt', 100, false],
            'lt below' => ['lt', 50, true],
            'lte above' => ['lte', 150, false],
            'lte equal' => ['lte', 100, true],
            'lte below' => ['lte', 50, true],
            'eq nan' => ['eq', NAN, false],
            'gte nan' => ['gte', NAN, false],
            'lt nan' => ['lt', NAN, false],
            'lte nan' => ['lte', NAN, false],
            'eq null' => ['eq', null, false],
            'gte null' => ['gte', null, false],
            'lt null' => ['lt', null, false],
            'lte null' => ['lte', null, false],
        ];
    }

    #[DataProvider('numberOperators')]
    public function testNumberOperators(string $operator, mixed $price, bool $expected): void
    {
        $definition = ExpressionDefinition::generated(new Field('price', FieldType::Number), $operator, '');

        $result = (new Evaluator())->evaluate(
            $definition,
            new ConfiguredExpression("price.{$operator}", ['value' => 100]),
            ['price' => $price],
        );

        $this->assertSame($expected, $result);
    }

    private function priceGreaterThan(mixed $subject, mixed $threshold): bool
    {
        return (new Evaluator())->evaluate(
            $this->priceGt,
            new ConfiguredExpression('price.gt', ['value' => $threshold]),
            $subject,
        );
    }
}
