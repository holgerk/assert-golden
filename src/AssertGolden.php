<?php

namespace Holgerk\AssertGolden;

use LogicException;
use PhpParser\NodeTraverser;
use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Assert;
use Symfony\Component\VarExporter\VarExporter;

trait AssertGolden
{
    public static function assertGolden($expected, $actual): void
    {
        if ($expected === null) {
            $expected = $actual;
            Insertion::register(VarExporter::export($actual));
        }

        Assert::assertEquals($expected, $actual);
    }
}