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
    /** @var Insertion[] */
    private static array $insertions = [];

    public static bool $forceGoldenUpdate = false;

    public static function register(string $replacement): void
    {
        $filePath = null;
        $lineToFind = null;
        foreach (debug_backtrace() as $stackItem) {
            $function = ($stackItem['function'] ?? '');
            if ($function === 'assertGolden' || $function === 'Holgerk\\AssertGolden\\assertGolden') {
                $filePath = $stackItem['file'];
                $lineToFind = $stackItem['line'];
                break;
            }
        }
        assert((bool) $filePath);

        $chunks = explode('.', InstalledVersions::getVersion('nikic/php-parser'));
        $majorVersion = $chunks[0];

        $parser = $majorVersion === '4'
            ? (new ParserFactory())->create(
                ParserFactory::PREFER_PHP7,
                new Emulative(['usedAttributes' => ['startLine', 'endLine', 'startFilePos', 'endFilePos']]))
            : (new ParserFactory())->createForHostVersion();
        $fileContent = file_get_contents($filePath);
        $ast = $parser->parse($fileContent);

        if ($majorVersion === '4') {
            // see: https://github.com/nikic/PHP-Parser/blob/4.x/doc/component/FAQ.markdown
            $traverser = new NodeTraverser;
            $traverser->addVisitor(new NodeConnectingVisitor);
            $traverser->traverse($ast);
        } else {
            // see: https://github.com/nikic/PHP-Parser/blob/master/doc/component/FAQ.markdown
            (new NodeTraverser(new NodeConnectingVisitor))->traverse($ast);
        }

        $nodeFinder = new NodeFinder();
        $node = $nodeFinder->findFirst($ast, function (Node $node) use($lineToFind, $majorVersion) : bool {
            if ($majorVersion === '4') {
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
            }
            return
                ($node instanceof Identifier || $node instanceof Name)
                && $node->name === 'assertGolden'
                && $node->getStartLine() === $lineToFind
                && $node->getEndLine() === $lineToFind;
        });
        assert($node !== null);

        /** @var Node $argumentNode */
        $argumentNode = $node->getAttribute('next');

        self::$insertions[] = new self(
            $filePath,
            $argumentNode->getStartFilePos(),
            $argumentNode->getEndFilePos(),
            $lineToFind,
            $replacement
        );

        if (count(self::$insertions) === 1) {
            register_shutdown_function(self::class . '::shutdown');
        }
    }

    public static function shutdown(): void
    {
        self::writeAndResetInsertions();
    }

    public static function writeAndResetInsertions(): void
    {
        $insertions = self::$insertions;
        self::$insertions = [];

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

        $insertionsByFile = [];
        foreach ($insertions as $insertion) {
            $insertionsByFile[$insertion->file][] = $insertion;
        }

        foreach ($insertionsByFile as $file => $insertions) {
            $content = file_get_contents($file);
            assert(!empty($content));
            foreach ($insertions as $insertion) {
                $indent = self::getIndent($insertion->startPos, $content);

                // add indention
                $replacement = $insertion->replacement;
                $replacementLines = explode("\n", $replacement);
                $replacement = implode("\n$indent", $replacementLines);

                // insert expectation
                $content = substr_replace(
                    $content,
                    $replacement,
                    $insertion->startPos,
                    $insertion->endPos - $insertion->startPos + 1
                );
            }
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
}
