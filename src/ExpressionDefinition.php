<?php

declare(strict_types=1);

namespace Ruleink;

/**
 * Custom expressions not tied to one field, like product.is_expensive, have a null field and field type.
 */
final readonly class ExpressionDefinition
{
    public function __construct(
        public string $key,
        public ?string $field,
        public string $operator,
        public ?FieldType $fieldType,
        public string $label,
    ) {
    }
}
