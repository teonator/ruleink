<?php

declare(strict_types=1);

namespace Ruleink;

final class ExpressionGenerator
{
    private const PHRASES = [
        'equals' => 'equals',
        'contains' => 'contains',
        'eq' => 'equals',
        'gt' => 'is greater than',
        'gte' => 'is greater than or equal to',
        'lt' => 'is less than',
        'lte' => 'is less than or equal to',
        'is' => 'is',
        'before' => 'is before',
        'after' => 'is after',
    ];

    /**
     * @return list<ExpressionDefinition> fields in schema order, operators in catalogue order
     */
    public function generate(Schema $schema): array
    {
        $definitions = [];

        foreach ($schema->fields() as $field) {
            $label = ucfirst(str_replace('_', ' ', $field->name));

            foreach ($field->type->operators() as $operator) {
                $definitions[] = new ExpressionDefinition(
                    key: "{$field->name}.{$operator}",
                    field: $field->name,
                    operator: $operator,
                    fieldType: $field->type,
                    label: $label . ' ' . self::PHRASES[$operator],
                );
            }
        }

        return $definitions;
    }
}
