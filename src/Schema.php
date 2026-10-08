<?php

declare(strict_types=1);

namespace Ruleink;

use InvalidArgumentException;

final class Schema
{
    /** @var array<string, Field> */
    private array $fields = [];

    /**
     * @param list<Field> $fields
     */
    public function __construct(
        public readonly string $name,
        array $fields,
    ) {
        foreach ($fields as $field) {
            $this->add($field);
        }
    }

    public function field(string $name): Field
    {
        return $this->fields[$name]
            ?? throw new InvalidArgumentException("Unknown field [{$name}].");
    }

    private function add(Field $field): void
    {
        if (isset($this->fields[$field->name])) {
            throw new InvalidArgumentException("Duplicate field [{$field->name}].");
        }

        $this->fields[$field->name] = $field;
    }
}
