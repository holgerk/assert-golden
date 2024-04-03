<?php

namespace Holgerk\AssertGolden;

trait AssertGolden
{
    public static function assertGolden(mixed $expected, mixed $actual, $message = ''): void
    {
        _internalAssertGolden($expected, $actual, $message);
    }
}