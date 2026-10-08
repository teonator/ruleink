<?php

declare(strict_types=1);

namespace Ruleink;

final readonly class ValueDeclaration
{
    public function __construct(
        public string $name,
        public FieldType $type,
        public bool $required = true,
    ) {
    }
}
