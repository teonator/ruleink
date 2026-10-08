<?php

declare(strict_types=1);

namespace Ruleink;

final readonly class ExpressionDefinition
{
    public function __construct(
        public string $key,
        public string $field,
        public string $operator,
        public FieldType $fieldType,
        public string $label,
    ) {
    }
}
