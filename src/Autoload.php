<?php

namespace Holgerk\AssertGolden;

use PHPUnit\Framework\Assert;
use Symfony\Component\VarExporter\VarExporter;

$index = array_search('--update-golden', $_SERVER['argv'], true);
if ($index !== false) {
    Insertion::$forceGoldenUpdate = true;

    // remove argument / hide it from phpunit bootstrap
    array_splice($_SERVER['argv'], $index, 1);
}

function assertGolden(mixed $expected, mixed $actual, $message = ''): void
{
    _internalAssertGolden($expected, $actual, $message);
}

/** @internal */
function _internalAssertGolden(mixed $expected, mixed $actual, $message = ''): void
{
    if ($expected === null || Insertion::$forceGoldenUpdate) {
        $expected = $actual;
        Insertion::register(VarExporter::export($actual));
    }

    Assert::assertEquals($expected, $actual, $message);
}
