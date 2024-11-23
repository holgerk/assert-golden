<?php

namespace Holgerk\AssertGolden;

use Composer\InstalledVersions;
use LogicException;
use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NodeConnectingVisitor;
use PhpParser\ParserFactory;

/** @internal */
final class Insertion
{
    /** @var array<int, Insertion[]> */
    private static array $insertions = [];

    public static bool $forceUpdateGolden = false;
    public static bool $verbose = true;

    public static function register(string $replacement): void
    {
        // find line and file path of assertGolden call...
        $filePath = null;
        $lineToFind = null;
        foreach (debug_backtrace() as $stackItem) {
            $function = ($stackItem['function'] ?? '');
            // ...either from trait or imported function
            if ($function === 'assertGolden' || $function === 'Holgerk\\AssertGolden\\assertGolden') {
                $filePath = $stackItem['file'];
                $lineToFind = $stackItem['line'];
                break;
            }
        }
        assert((bool) $filePath);

        if (PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 1) {
            $lineToFind = self::fixLineToFindForPhp81($filePath, $lineToFind);
        }

        $nullArgumentNode = self::getNullArgumentNode($filePath, $lineToFind);

        // create insertion in context of current process id, so that each process writes only its own insertions
        self::$insertions[self::getProcessId()][] = new self(
            $filePath,
            $nullArgumentNode->getStartFilePos(),
            $nullArgumentNode->getEndFilePos(),
            $lineToFind,
            $replacement
        );

        if (count(self::$insertions) === 1) {
            register_shutdown_function(self::class . '::writeAndResetInsertions');
        }
    }

    public static function writeAndResetInsertions(): void
    {
        // only write and reset insertions from this process
        // (every forked process will have and execute the registered shutdown function)
        $insertions = self::$insertions[self::getProcessId()] ?? [];
        self::$insertions[self::getProcessId()] = [];

        if (empty($insertions)) {
            return;
        }

        self::output(PHP_EOL);
        self::output('assertGolden | Writing insertions' . PHP_EOL);
        self::output('assertGolden | ==================' . PHP_EOL);

        // arrange insertions starting from the end of the file to prevent disrupting the positions during replacements
        usort($insertions, function (Insertion $a, Insertion $b): int {
            if ($a->file !== $b->file) {
                return $a->file <=> $b->file;
            }

            if ($a->lineNumber === $b->lineNumber) {
                throw new LogicException(
                    "Could not process multiple automatic replacement on the same line, see:\n"
                    . "  file: $b->file and line: $b->lineNumber"
                );
            }

            return $b->startPos <=> $a->startPos;
        });

        // process all insertion in the context of the corresponding file
        /** @var array<string, Insertion[]> $insertionsByFile */
        $insertionsByFile = [];
        foreach ($insertions as $insertion) {
            $insertionsByFile[$insertion->file][] = $insertion;
        }

        foreach ($insertionsByFile as $file => $insertions) {
            self::output('assertGolden | reading: ' . $file . PHP_EOL);
            $content = file_get_contents($file);
            foreach ($insertions as $insertion) {
                $indent = self::getIndent($insertion->startPos, $content);

                // add indention
                $replacement = $insertion->replacement;
                $replacementLines = explode("\n", $replacement);
                $replacement = implode("\n$indent", $replacementLines);

                // insert expectation
                self::output('assertGolden | update assertion at line: ' . $insertion->lineNumber . PHP_EOL);
                $content = substr_replace(
                    $content,
                    $replacement,
                    $insertion->startPos,
                    $insertion->endPos - $insertion->startPos + 1
                );
            }
            self::output('assertGolden | writing: ' . $file . PHP_EOL);
            file_put_contents($file, $content);
        }
    }

    private static function getIndent(int $startPos, string $content): string
    {
        // detect start of line position
        $offset = 0;
        $startOfLine = 0;
        while (true) {
            $offset -= 1;
            $charPos = $startPos + $offset;
            if ($charPos < 0) {
                break;
            }
            $char = $content[$charPos];
            if ($char === "\n" || $char === "\r") {
                $startOfLine = $startPos + $offset + 1;
                break;
            }
        }
        // detect indention
        $indent = '';
        $offset = 0;
        while (true) {
            $charPos = $startOfLine + $offset;
            $char = $content[$charPos];
            if ($char === ' ' || $char === "\t") {
                $indent .= $char;
            } else {
                break;
            }
            $offset += 1;
        }

        return $indent;
    }

    private function __construct(
        public string $file,
        public int $startPos,
        public int $endPos,
        public int $lineNumber,
        public string $replacement,
    ) {
    }

    private static function output(string $message): void
    {
        if (! self::$verbose) {
            return;
        }
        echo $message;
    }

    private static function getProcessId(): int
    {
        return getmypid();
    }

    private static function fixLineToFindForPhp81($filePath, $lineToFind): int
    {
        // in php 8.1 and lower debug_backtrace reports the line of the last argument of the assertGolden call,
        // so we need to find the line of the call
        // (see: https://3v4l.org/Q4aji)
        $lines = file($filePath);
        while (! str_contains($lines[$lineToFind], 'assertGolden')) {
            $lineToFind--;
        }
        // convert to 1-based line-numbers
        $lineToFind += 1;
        return $lineToFind;
    }

    private static function getNullArgumentNode($filePath, $lineToFind): Node
    {
        $chunks = explode('.', InstalledVersions::getVersion('nikic/php-parser'));
        $majorVersion = $chunks[0];
        if ($majorVersion === '4') {
            return self::getNullArgumentNodeParserV4($filePath, $lineToFind);
        }
        return self::getNullArgumentNodeParserV5($filePath, $lineToFind);
    }

    private static function getNullArgumentNodeParserV4($filePath, $lineToFind): Node
    {
        $parser = (new ParserFactory())->create(
            ParserFactory::PREFER_PHP7,
            new Emulative(['usedAttributes' => ['startLine', 'endLine', 'startFilePos', 'endFilePos']])
        );
        $fileContent = file_get_contents($filePath);
        $ast = $parser->parse($fileContent);

        // see: https://github.com/nikic/PHP-Parser/blob/4.x/doc/component/FAQ.markdown
        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NodeConnectingVisitor);
        $traverser->traverse($ast);

        $nodeFinder = new NodeFinder();
        $node = $nodeFinder->findFirst($ast, function (Node $node) use($lineToFind) : bool {
            return
                (
                    // match method or static call
                    ($node instanceof Identifier && $node->name === 'assertGolden')
                    ||
                    // match function call
                    ($node instanceof Name && $node->getParts()[0] === 'assertGolden')
                )
                && $node->getStartLine() === $lineToFind
                && $node->getEndLine() === $lineToFind;
        });
        assert($node !== null);

        /** @var Node $argumentNode */
        $argumentNode = $node->getAttribute('next');

        return $argumentNode;
    }

    private static function getNullArgumentNodeParserV5($filePath, $lineToFind): Node
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $fileContent = file_get_contents($filePath);
        $ast = $parser->parse($fileContent);

        // see: https://github.com/nikic/PHP-Parser/blob/master/doc/component/FAQ.markdown
        (new NodeTraverser(new NodeConnectingVisitor))->traverse($ast);

        $nodeFinder = new NodeFinder();
        $node = $nodeFinder->findFirst($ast, function (Node $node) use($lineToFind) : bool {
            return
                ($node instanceof Identifier || $node instanceof Name)
                && $node->name === 'assertGolden'
                && $node->getStartLine() === $lineToFind
                && $node->getEndLine() === $lineToFind;
        });
        assert($node !== null);

        /** @var Node $argumentNode */
        $argumentNode = $node->getAttribute('next');

        return $argumentNode;
    }



}
