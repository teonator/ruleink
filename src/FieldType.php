<?php

declare(strict_types=1);

namespace Ruleink;

enum FieldType: string
{
    case String = 'string';
    case Number = 'number';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';

    /**
     * The expression catalogue: the operators every field of this type gets.
     *
     * @return list<string>
     */
    public function operators(): array
    {
        return match ($this) {
            self::String => ['equals', 'contains'],
            self::Number => ['eq', 'gt', 'gte', 'lt', 'lte'],
            self::Boolean => ['is'],
            self::Date, self::DateTime => ['before', 'after'],
        };
    }
}
