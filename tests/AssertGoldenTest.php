<?php

namespace Holgerk\AssertGolden\Tests;

use Holgerk\AssertGolden\Insertion;
use PHPUnit\Framework\TestCase;

class AssertGoldenTest extends TestCase
{
    public function setUp(): void
    {
        foreach (glob(__DIR__ . '/cases/*.golden.php') as $goldenFile) {
            unlink($goldenFile);
        }
    }

    public static function casesDataProvider(): array
    {
        return [
            'StaticCall' => ['case' => 'StaticCall'],
            'MethodCall' => ['case' => 'MethodCall'],
            'FunctionCall' => ['case' => 'FunctionCall'],
        ];
    }

    /**
     * @test
     * @dataProvider casesDataProvider
     */
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

        // they all generate the same golden file
        $goldenFile = $dir . '/' . $case . '.test_test.golden.php';
        self::assertFileEquals($dir . '/golden.expected.php', $goldenFile);
    }

}
