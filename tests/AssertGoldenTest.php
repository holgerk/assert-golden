<?php

namespace Holgerk\AssertGolden\Tests;

use Holgerk\AssertGolden\Insertion;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AssertGoldenTest extends TestCase
{
    public function tearDown(): void
    {
        copy(
            __DIR__ . '/TestFile.before.php',
            __DIR__ . '/TestFile.php'
        );
    }

    #[Test]
    public function file_is_changed(): void
    {
        $example = new TestFile();
        $example->test();
        Insertion::writeAndResetInsertions();
        self::assertFileEquals(
            __DIR__ . '/TestFile.expected.php',
            __DIR__ . '/TestFile.php'
        );
    }


}
