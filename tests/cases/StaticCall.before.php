<?php

namespace Holgerk\AssertGolden\Tests;

use Holgerk\AssertGolden\AssertGolden;

class StaticCall
{
    use AssertGolden;

    public function test(): void
    {
        self::assertGolden(
            null,
            ['a' => 1, 'b' => 2]
        );
    }
}