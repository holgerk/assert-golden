<?php

namespace Holgerk\AssertGolden\Tests;

use Holgerk\AssertGolden\AssertGolden;

class StaticCall
{
    use AssertGolden;

    public function test(): void
    {
        self::assertGolden(
            [
                'a' => 1,
                'b' => 2,
            ],
            ['a' => 1, 'b' => 2]
        );
        self::assertGoldenFile(['a' => 1, 'b' => 2]);
    }
}