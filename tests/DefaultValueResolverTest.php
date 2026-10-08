<?php

declare(strict_types=1);

namespace Ruleink\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ruleink\DefaultValueResolver;

final class DefaultValueResolverTest extends TestCase
{
    public static function subjects(): array
    {
        $object = new \stdClass();
        $object->price = 10;
        $object->note = null;

        $private = new class {
            private int $price = 10;
        };

        return [
            'array key' => [['price' => 10], 'price', 10],
            'array key with null' => [['price' => null], 'price', null],
            'array missing key' => [['title' => 'x'], 'price', null],
            'array falsy value' => [['price' => 0], 'price', 0],
            'object property' => [$object, 'price', 10],
            'object property with null' => [$object, 'note', null],
            'object missing property' => [$object, 'stock', null],
            'object private property' => [$private, 'price', null],
            'null subject' => [null, 'price', null],
            'string subject' => ['price', 'price', null],
            'int subject' => [10, 'price', null],
        ];
    }

    #[DataProvider('subjects')]
    public function testGet(mixed $subject, string $path, mixed $expected): void
    {
        $this->assertSame($expected, (new DefaultValueResolver())->get($subject, $path));
    }
}
