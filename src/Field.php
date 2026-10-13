<?php

declare(strict_types=1);

namespace RuleInk;

final readonly class Field
{
    public function __construct(
        public string $name,
        public FieldType $type,
    ) {
    }
}
