<?php

namespace Holgerk\AssertGolden\Tests;

use function Holgerk\AssertGolden\assertGolden;
use function Holgerk\AssertGolden\assertGoldenFile;

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
        assertGoldenFile(['a' => 1, 'b' => 2]);
    }
}