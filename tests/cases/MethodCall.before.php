<?php

namespace Holgerk\AssertGolden\Tests;

use Holgerk\AssertGolden\AssertGolden;

class MethodCall
{
    use AssertGolden;

    public function test(): void
    {
        $this->assertGolden(
            null,
            ['a' => 1, 'b' => 2]
        );
        $this->assertGoldenFile(['a' => 1, 'b' => 2]);
    }
}