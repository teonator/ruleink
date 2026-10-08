<?php

declare(strict_types=1);

namespace Ruleink\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ruleink\ExpressionDefinition;
use Ruleink\Field;
use Ruleink\FieldType;
use Ruleink\Ruleink;
use Ruleink\Schema;
use UnexpectedValueException;

final class RuleinkTest extends TestCase
{
    public function testExpressionsForProduct(): void
    {
        $this->assertSame(
            [
                'name.equals',
                'name.contains',
                'price.eq',
                'price.gt',
                'price.gte',
                'price.lt',
                'price.lte',
                'active.is',
                'released_at.before',
                'released_at.after',
            ],
            array_column($this->ruleink()->expressionsFor('product'), 'key'),
        );
    }

    public function testEvaluatesAGeneratedExpressionByKey(): void
    {
        $ruleink = $this->ruleink();

        $this->assertTrue($ruleink->evaluate('product', ['price' => 150], 'price.gt', ['value' => 100]));
        $this->assertFalse($ruleink->evaluate('product', ['price' => 50], 'price.gt', ['value' => 100]));
    }

    public function testSameKeyIsLookedUpInTheGivenSchema(): void
    {
        $ruleink = $this->ruleink();
        $ruleink->register(new Schema('order', [new Field('price', FieldType::String)]));

        $this->assertTrue($ruleink->evaluate('order', ['price' => 'free'], 'price.equals', ['value' => 'free']));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown expression [price.equals] in schema [product].');

        $ruleink->evaluate('product', ['price' => 'free'], 'price.equals', ['value' => 'free']);
    }

    public function testCustomExpressionIsListedAfterGeneratedOnes(): void
    {
        $ruleink = $this->ruleink();
        $ruleink->define('product', $this->isExpensive(), fn (mixed $subject, array $values): bool => true);

        $definitions = $ruleink->expressionsFor('product');

        $this->assertCount(11, $definitions);
        $this->assertEquals($this->isExpensive(), end($definitions));
    }

    public function testEvaluatesACustomExpressionWithItsValues(): void
    {
        $ruleink = $this->ruleink();
        $ruleink->define(
            'product',
            $this->isExpensive(),
            fn (mixed $subject, array $values): bool => $subject['price'] >= ($values['threshold'] ?? 1000),
        );

        $this->assertTrue($ruleink->evaluate('product', ['price' => 1500], 'product.is_expensive'));
        $this->assertFalse(
            $ruleink->evaluate('product', ['price' => 1500], 'product.is_expensive', ['threshold' => 2000]),
        );
    }

    public function testCustomExpressionMustReturnABool(): void
    {
        $ruleink = $this->ruleink();
        $ruleink->define('product', $this->isExpensive(), fn (mixed $subject, array $values): mixed => 1);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Custom expression [product.is_expensive] must return a bool, got int.');

        $ruleink->evaluate('product', ['price' => 1500], 'product.is_expensive');
    }

    public function testCustomExpressionCannotReuseAGeneratedKey(): void
    {
        $definition = new ExpressionDefinition('price.gt', 'price', 'gt', FieldType::Number, 'Custom gt');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate expression [price.gt] in schema [product].');

        $this->ruleink()->define('product', $definition, fn (mixed $subject, array $values): bool => true);
    }

    public function testCustomExpressionNeedsARegisteredSchema(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown schema [product].');

        (new Ruleink())->define('product', $this->isExpensive(), fn (mixed $subject, array $values): bool => true);
    }

    public function testUnknownSchemaThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown schema [order].');

        $this->ruleink()->evaluate('order', [], 'price.gt', ['value' => 1]);
    }

    private function ruleink(): Ruleink
    {
        $ruleink = new Ruleink();
        $ruleink->register(ExpressionGeneratorTest::product());

        return $ruleink;
    }

    private function isExpensive(): ExpressionDefinition
    {
        return new ExpressionDefinition(
            key: 'product.is_expensive',
            field: null,
            operator: 'is_expensive',
            fieldType: null,
            label: 'Product is expensive',
        );
    }
}
