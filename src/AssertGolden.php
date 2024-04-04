<?php

namespace Holgerk\AssertGolden;

trait AssertGolden
{
    /**
     * Same as assertEquals, but if null is passed as expectation, null is automatically replaced with
     * the actual value
     */
    public static function assertGolden(mixed $expected, mixed $actual, string $message = ''): void
    {
        _internalAssertGolden($expected, $actual, $message);
    }
}