<?php

declare(strict_types=1);

namespace Ruleink;

final readonly class AuthorError
{
    /**
     * @param ?string $value the value name at fault, or null when the expression key itself is wrong
     */
    public function __construct(
        public string $message,
        public ?string $value = null,
    ) {
    }
}
