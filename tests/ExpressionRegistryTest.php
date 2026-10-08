<?php

declare(strict_types=1);

namespace Ruleink\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
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
