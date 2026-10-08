<?php

declare(strict_types=1);

namespace Ruleink\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Ruleink\Field;
use Ruleink\FieldType;
use Ruleink\Schema;
use TypeError;

final class SchemaTest extends TestCase
{
    public function testNameIsRetained(): void
    {
        $schema = new Schema('product', [new Field('price', FieldType::Number)]);

        $this->assertSame('product', $schema->name);
    }

    public function testFieldReturnsFieldByName(): void
    {
        $price = new Field('price', FieldType::Number);
        $schema = new Schema('product', [$price, new Field('title', FieldType::String)]);

        $this->assertSame($price, $schema->field('price'));
    }

    public function testFieldThrowsForUnknownName(): void
    {
        $schema = new Schema('product', [new Field('price', FieldType::Number)]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown field [stock].');

        $schema->field('stock');
    }

    public function testConstructorRejectsDuplicateFieldNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate field [price].');

        new Schema('product', [new Field('price', FieldType::Number), new Field('price', FieldType::String)]);
    }

    public function testConstructorRejectsNonFieldEntries(): void
    {
        $this->expectException(TypeError::class);

        new Schema('product', ['price']);
    }
}
