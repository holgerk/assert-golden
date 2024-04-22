<?php

namespace Holgerk\AssertGolden;

use PHPUnit\Framework\Assert;
use Symfony\Component\VarExporter\VarExporter;

$index = array_search('--update-golden', $_SERVER['argv'], true);
if ($index !== false) {
    Insertion::$forceUpdateGolden = true;

    // remove argument / hide it from phpunit bootstrap
    array_splice($_SERVER['argv'], $index, 1);
}

/**
 * Same as `assertEquals`, but when `null` is given as argument, the test file is automatically edited and `null`
 * is substituted with the actual value
 */
function assertGolden(mixed $expected, mixed $actual, string $message = ''): void
{
    _internalAssertGolden($expected, $actual, $message);
}

/** @internal */
function _internalAssertGolden(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected === null || Insertion::$forceUpdateGolden) {
        $expected = $actual;
        Insertion::register(VarExporter::export($actual));
    }

    Assert::assertEquals($expected, $actual, $message);
}
