<?php

declare(strict_types=1);

namespace Ruleink\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ruleink\ExpressionDefinition;
use Ruleink\ExpressionGenerator;
use Ruleink\ExpressionRegistry;
use Ruleink\Field;
use Ruleink\FieldType;
use Ruleink\Schema;

final class ExpressionRegistryTest extends TestCase
{
    public function testExpressionsForReturnsTheGeneratedDefinitions(): void
    {
        $registry = new ExpressionRegistry();
        $registry->register(ExpressionGeneratorTest::product());

        $this->assertEquals(
            (new ExpressionGenerator())->generate(ExpressionGeneratorTest::product()),
            $registry->expressionsFor('product'),
        );
    }

    public function testSchemasAreKeptApart(): void
    {
        $registry = new ExpressionRegistry();
        $registry->register(new Schema('product', [new Field('price', FieldType::Number)]));
        $registry->register(new Schema('order', [new Field('paid', FieldType::Boolean)]));

        $this->assertSame(['paid.is'], array_column($registry->expressionsFor('order'), 'key'));
        $this->assertCount(5, $registry->expressionsFor('product'));
    }

    public function testSchemaWithoutFieldsIsStillRegistered(): void
    {
        $registry = new ExpressionRegistry();
        $registry->register(new Schema('empty', []));

        $this->assertSame([], $registry->expressionsFor('empty'));
    }

    public function testDefinitionLooksUpByKey(): void
    {
        $registry = new ExpressionRegistry();
        $registry->register(ExpressionGeneratorTest::product());

        $this->assertSame('Price is greater than', $registry->find('product', 'price.gt')?->label);
    }

    public function testUnknownKeyIsNull(): void
    {
        $registry = new ExpressionRegistry();
        $registry->register(ExpressionGeneratorTest::product());

        $this->assertNull($registry->find('product', 'price.between'));
    }

    public function testFindInUnknownSchemaThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown schema [product].');

        (new ExpressionRegistry())->find('product', 'price.gt');
    }

    public function testAddedDefinitionsFollowGeneratedOnes(): void
    {
        $custom = ExpressionDefinition::custom('order.is_late', 'Order is late');
        $registry = new ExpressionRegistry();
        $registry->register(new Schema('order', [new Field('paid', FieldType::Boolean)]));
        $registry->add('order', $custom);

        $this->assertSame(['paid.is', 'order.is_late'], array_column($registry->expressionsFor('order'), 'key'));
        $this->assertSame($custom, $registry->find('order', 'order.is_late'));
    }

    public function testUnknownSchemaThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown schema [product].');

        (new ExpressionRegistry())->expressionsFor('product');
    }

    public function testDuplicateSchemaThrows(): void
    {
        $registry = new ExpressionRegistry();
        $registry->register(new Schema('product', []));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate schema [product].');

        $registry->register(new Schema('product', []));
    }
}
