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
            $stackItem = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
            $filePath = $stackItem['file'];
            $line = $stackItem['line'];

            // php < 8.2 reports the last line of the call and not the first line
            // see: https://onlinephp.io/c/b2538
            // ...so we need to parse the source code:
            if (version_compare(PHP_VERSION, '8.2.0', '<')) {
                $line = self::getStartLine($filePath, $line);
            }

            $lines = file($filePath);
            $replacedLine = $lines[$line];
            preg_match('/^(?<indention> *)null,$/', $replacedLine, $matches);
            if (empty($matches)) {
                throw new LogicException("Could not replace line: '$replacedLine', expected line to contain only: 'null,'!");
            }
            $linesToInsert = array_map(
                static fn ($line) => $matches['indention'] . $line . "\n",
                explode("\n", VarExporter::export($actual) . ',')
            );
            array_splice($lines, $line, 1, $linesToInsert);
            file_put_contents($filePath, implode('', $lines));
        } else {
            Assert::assertEquals($expected, $actual);
        }
    }

    private static function getStartLine(string $filePath, string $lineToFind): int
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, new Emulative([
            'usedAttributes' => [
                'startLine',
                'endLine',
            ]
        ]));
        $fileContent = file_get_contents($filePath);
        $ast = $parser->parse($fileContent);

        $traverser = new NodeTraverser();
        $visitor = new class($lineToFind) extends NodeVisitorAbstract {
            public function __construct(
                private readonly int $lineToFind,
                public int $startLine = -1,
            ) {
            }

            public function enterNode(Node $node)
            {
                if (
                    $node instanceof Node\Expr\StaticCall &&
                    $node->name->name === 'assertGolden' &&
                    $node->getStartLine() <= $this->lineToFind &&
                    $node->getEndLine() >= $this->lineToFind
                ) {
                    $this->startLine = $node->getStartLine();
                    return NodeTraverser::STOP_TRAVERSAL;
                }
            }
        };
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->startLine;
    }
}