<?php

declare(strict_types=1);

namespace Ruleink;

final readonly class Field
{
    public function __construct(
        public string $name,
        public FieldType $type,
    ) {
    }
}
