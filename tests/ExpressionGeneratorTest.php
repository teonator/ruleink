<?php

declare(strict_types=1);

namespace Ruleink\Tests;

use PHPUnit\Framework\TestCase;
use Ruleink\ExpressionGenerator;
use Ruleink\Field;
use Ruleink\FieldType;
use Ruleink\Schema;
use Ruleink\ValueDeclaration;

final class ExpressionGeneratorTest extends TestCase
{
    public function testGeneratesTheCatalogueForEachFieldInOrder(): void
    {
        $definitions = (new ExpressionGenerator())->generate(self::product());

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
            array_column($definitions, 'key'),
        );
    }

    public function testDefinitionDescribesFieldOperatorAndLabel(): void
    {
        $definitions = (new ExpressionGenerator())->generate(self::product());

        $gte = $definitions[4];

        $this->assertSame('price.gte', $gte->key);
        $this->assertSame('price', $gte->field);
        $this->assertSame('gte', $gte->operator);
        $this->assertSame(FieldType::Number, $gte->fieldType);
        $this->assertSame('Price is greater than or equal to', $gte->label);
        $this->assertEquals([new ValueDeclaration('value', FieldType::Number)], $gte->values);
        $this->assertSame('Released at is before', $definitions[8]->label);
        $this->assertEquals([new ValueDeclaration('value', FieldType::DateTime)], $definitions[8]->values);
    }

    public function testDateFieldsGetTheDateCatalogue(): void
    {
        $schema = new Schema('event', [new Field('starts_on', FieldType::Date)]);

        $definitions = (new ExpressionGenerator())->generate($schema);

        $this->assertSame(['starts_on.before', 'starts_on.after'], array_column($definitions, 'key'));
        $this->assertSame(FieldType::Date, $definitions[0]->fieldType);
    }

    public function testSchemaWithoutFieldsGeneratesNothing(): void
    {
        $this->assertSame([], (new ExpressionGenerator())->generate(new Schema('empty', [])));
    }

    public static function product(): Schema
    {
        return new Schema('product', [
            new Field('name', FieldType::String),
            new Field('price', FieldType::Number),
            new Field('active', FieldType::Boolean),
            new Field('released_at', FieldType::DateTime),
        ]);
    }
}
