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
}
