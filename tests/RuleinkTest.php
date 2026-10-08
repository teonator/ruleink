<?php

declare(strict_types=1);

namespace Ruleink\Tests;

use PHPUnit\Framework\TestCase;
use Ruleink\Ruleink;

final class RuleinkTest extends TestCase
{
    public function testExpressionsForProduct(): void
    {
        $ruleink = new Ruleink();
        $ruleink->register(ExpressionGeneratorTest::product());

        $this->assertSame(
            [
                'name.equals',
                'name.contains',
                'price.eq',
                'price.gt',
                'price.gte',
                'price.lt',
                'price.lte',
                'active.is',
                'released_at.before',
                'released_at.after',
            ],
            array_column($ruleink->expressionsFor('product'), 'key'),
        );
    }
}
