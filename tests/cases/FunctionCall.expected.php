<?php

namespace Holgerk\AssertGolden\Tests;

use function Holgerk\AssertGolden\assertGolden;

class FunctionCall
{
    public function test(): void
    {
        assertGolden(
            [
                'a' => 1,
                'b' => 2,
            ],
            ['a' => 1, 'b' => 2]
        );
    }
}