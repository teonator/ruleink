<?php

declare(strict_types=1);

namespace Ruleink;

use InvalidArgumentException;

/**
 * Built only through generated() or custom(), so a generated expression always declares the value its operator needs.
 */
final readonly class ExpressionDefinition
{
    /**
     * @param list<ValueDeclaration> $values
     */
    private function __construct(
        public string $key,
        public ?string $field,
        public ?string $operator,
        public ?FieldType $fieldType,
        public string $label,
        public array $values,
    ) {
    }

    public static function generated(Field $field, string $operator, string $label): self
    {
        return new self(
            "{$field->name}.{$operator}",
            $field->name,
            $operator,
            $field->type,
            $label,
            [new ValueDeclaration('value', $field->type)],
        );
    }

    /**
     * Custom expressions have no operator and may concern one field.
     *
     * @param list<ValueDeclaration> $values
     */
    public static function custom(string $key, string $label, array $values = [], ?Field $field = null): self
    {
        $names = array_column($values, 'name');

        if (count($names) !== count(array_unique($names))) {
            throw new InvalidArgumentException("Expression [{$key}] declares the same value name more than once.");
        }

        return new self($key, $field?->name, null, $field?->type, $label, $values);
    }
}
