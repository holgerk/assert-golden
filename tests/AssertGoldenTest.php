<?php

namespace Holgerk\AssertGolden\Tests;

use Holgerk\AssertGolden\Insertion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AssertGoldenTest extends TestCase
{
    public static function casesDataProvider(): array
    {
        return [
            'StaticCall' => ['case' => 'StaticCall'],
            'MethodCall' => ['case' => 'MethodCall'],
            'FunctionCall' => ['case' => 'FunctionCall'],
        ];
    }

    #[Test, DataProvider('casesDataProvider')]
    public function file_is_changed(string $case): void
    {
        $dir = __DIR__ . '/cases';
        $beforeFile = $dir . '/' . $case . '.before.php';
        $expectedFile = $dir . '/' . $case . '.expected.php';
        $testFile = $dir . '/' . $case . '.test.php';
        copy($beforeFile, $testFile);
        include $testFile;

        $class = 'Holgerk\\AssertGolden\\Tests\\' . $case;
        $example = new $class();
        $example->test();
        Insertion::writeAndResetInsertions();

        self::assertFileEquals($expectedFile, $testFile);
    }

}
