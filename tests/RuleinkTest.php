<?php

declare(strict_types=1);

namespace Ruleink\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ruleink\ExpressionDefinition;
use Ruleink\Field;
use Ruleink\FieldType;
use Ruleink\InvalidConfiguredExpression;
use Ruleink\Ruleink;
use Ruleink\Schema;
use Ruleink\ValueDeclaration;
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

        $this->expectException(InvalidConfiguredExpression::class);
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
        $definition = ExpressionDefinition::custom('price.gt', 'Custom gt');

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

    public function testValidateListsEveryAuthorError(): void
    {
        $errors = $this->ruleink()->validate('product', 'price.gt', ['vlaue' => 100, 'extra' => 1]);

        $this->assertSame(
            ['Missing value [value].', 'Unknown value [vlaue].', 'Unknown value [extra].'],
            array_column($errors, 'message'),
        );
        $this->assertSame(['value', 'vlaue', 'extra'], array_column($errors, 'value'));
    }

    public function testValidateReportsAnUnknownKeyWithoutAValueName(): void
    {
        $errors = $this->ruleink()->validate('product', 'price.between', ['value' => 1]);

        $this->assertCount(1, $errors);
        $this->assertSame('Unknown expression [price.between] in schema [product].', $errors[0]->message);
        $this->assertNull($errors[0]->value);
    }

    public function testValidValuesHaveNoErrors(): void
    {
        $this->assertSame([], $this->ruleink()->validate('product', 'price.gt', ['value' => '100.5']));
    }

    public function testRequiredValueSetToNullIsATypeError(): void
    {
        $errors = $this->ruleink()->validate('product', 'name.equals', ['value' => null]);

        $this->assertSame(['Value [value] must be a string.'], array_column($errors, 'message'));
    }

    public function testPositionalValuesAreUnknown(): void
    {
        $errors = $this->ruleink()->validate('product', 'price.gt', [100]);

        $this->assertSame(['Missing value [value].', 'Unknown value [0].'], array_column($errors, 'message'));
    }

    public function testInvalidConfiguredExpressionCarriesTheDetails(): void
    {
        try {
            $this->ruleink()->evaluate('product', ['price' => 1], 'price.gt', ['value' => 'abc']);
            $this->fail('Expected InvalidConfiguredExpression.');
        } catch (InvalidConfiguredExpression $exception) {
            $this->assertSame('product', $exception->schema);
            $this->assertSame('price.gt', $exception->key);
            $this->assertSame('value', $exception->errors[0]->value);
            $this->assertSame(
                'Invalid configured expression [price.gt]: Value [value] must be a finite number.',
                $exception->getMessage(),
            );
        }
    }

    public function testDeveloperErrorsAreNotAuthorErrors(): void
    {
        try {
            $this->ruleink()->evaluate('order', [], 'price.gt', ['value' => 1]);
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            $this->assertNotInstanceOf(InvalidConfiguredExpression::class, $exception);
        }
    }

    public function testCustomValuesAreValidatedBeforeTheEvaluatorRuns(): void
    {
        $ruleink = $this->ruleink();
        $ruleink->define('product', $this->isExpensive(), function (mixed $subject, array $values): bool {
            throw new \LogicException('Evaluator should not run.');
        });

        $this->expectException(InvalidConfiguredExpression::class);
        $this->expectExceptionMessage('Value [threshold] must be a finite number.');

        $ruleink->evaluate('product', ['price' => 1500], 'product.is_expensive', ['threshold' => 'lots']);
    }

    public function testDefineRejectsGeneratedExpressions(): void
    {
        $definition = ExpressionDefinition::generated(new Field('stock', FieldType::Number), 'gt', 'Stock');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expression [stock.gt] is generated; define() only takes custom expressions.');

        $this->ruleink()->define('product', $definition, fn (mixed $subject, array $values): bool => true);
    }

    public function testCustomExpressionRejectsDuplicateValueNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expression [product.is_new] declares the same value name more than once.');

        ExpressionDefinition::custom('product.is_new', 'Product is new', [
            new ValueDeclaration('input', FieldType::Number),
            new ValueDeclaration('input', FieldType::Date),
        ]);
    }

    public function testCustomExpressionMayConcernOneField(): void
    {
        $price = new Field('price', FieldType::Number);
        $definition = ExpressionDefinition::custom('price.is_round', 'Price is round', field: $price);
        $ruleink = $this->ruleink();
        $ruleink->define('product', $definition, fn (mixed $subject, array $values): bool => $subject['price'] === 300);

        $this->assertSame('price', $definition->field);
        $this->assertSame(FieldType::Number, $definition->fieldType);
        $this->assertNull($definition->operator);
        $this->assertTrue($ruleink->evaluate('product', ['price' => 300], 'price.is_round'));
    }

    private function isExpensive(): ExpressionDefinition
    {
        return ExpressionDefinition::custom(
            'product.is_expensive',
            'Product is expensive',
            [new ValueDeclaration('threshold', FieldType::Number, required: false)],
        );
    }
}
