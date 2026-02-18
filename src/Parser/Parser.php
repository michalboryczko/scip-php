<?php

declare(strict_types=1);

namespace ScipPhp\Parser;

use Closure;
use Override;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser as PhpParser;
use PhpParser\ParserFactory;
use RuntimeException;
use ScipPhp\File\Reader;

final class Parser
{
    private ParentConnectingVisitor $parentConnectingVisitor;

    private NameResolver $nameResolver;

    private PhpParser $parser;

    /** @var array<non-empty-string, array{stmts: list<Node\Stmt>, code: non-empty-string}> */
    private array $cache = [];

    public function __construct()
    {
        $this->parentConnectingVisitor = new ParentConnectingVisitor();
        $this->nameResolver = new NameResolver();
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @param  non-empty-string  $filename
     * @param  Closure(PosResolver, Node): void  $visitor
     * @param  ?Closure(Node): void  $enterVisitor  Optional callback on enterNode (for scope tracking)
     * @param  ?Closure(Node): void  $leaveAfterVisitor  Optional callback after leaveNode visitor (for scope cleanup)
     */
    public function traverse(
        string $filename,
        object $newThis,
        Closure $visitor,
        ?Closure $enterVisitor = null,
        ?Closure $leaveAfterVisitor = null,
    ): void {
        if (isset($this->cache[$filename])) {
            $stmts = $this->cache[$filename]['stmts'];
            $code = $this->cache[$filename]['code'];
        } else {
            $code = Reader::read($filename);
            if ($code === '') {
                throw new RuntimeException("Cannot parse file: {$filename}.");
            }

            $stmts = $this->parser->parse($code);
            if ($stmts === null) {
                throw new RuntimeException("Cannot parse file: {$filename}.");
            }

            $this->cache[$filename] = ['stmts' => $stmts, 'code' => $code];
        }

        $pos = new PosResolver($code);

        $t = new NodeTraverser(
            $this->nameResolver,
            $this->parentConnectingVisitor,
            new class ($pos, $newThis, $visitor, $enterVisitor, $leaveAfterVisitor) extends NodeVisitorAbstract
            {
                public function __construct(
                    private readonly PosResolver $pos,
                    private readonly object $newThis,
                    private readonly Closure $visitor,
                    private readonly ?Closure $enterVisitor,
                    private readonly ?Closure $leaveAfterVisitor,
                ) {
                }

                #[Override]
                public function enterNode(Node $n): ?Node
                {
                    $this->enterVisitor?->call($this->newThis, $n);
                    return null;
                }

                #[Override]
                public function leaveNode(Node $n): ?Node
                {
                    $this->visitor->call($this->newThis, $this->pos, $n);
                    $this->leaveAfterVisitor?->call($this->newThis, $n);
                    return null;
                }
            },
        );

        $t->traverse($stmts);
    }

    /**
     * Clear the AST cache to free memory.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
