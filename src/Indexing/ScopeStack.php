<?php

declare(strict_types=1);

namespace ScipPhp\Indexing;

use PhpParser\Node\Stmt\ClassLike;

final class ScopeStack
{
    /** @var list<ScopeFrame> */
    private array $frames = [];

    public function push(ScopeFrame $frame): void
    {
        $this->frames[] = $frame;
    }

    public function pop(): void
    {
        array_pop($this->frames);
    }

    public function reset(): void
    {
        $this->frames = [];
    }

    /**
     * Get the current namespace prefix (e.g., "App/Models/") or empty string.
     */
    public function getNamespace(): string
    {
        for ($i = count($this->frames) - 1; $i >= 0; $i--) {
            if ($this->frames[$i]->namespace !== '') {
                return $this->frames[$i]->namespace;
            }
            if ($this->frames[$i]->kind === 'namespace') {
                return '';
            }
        }
        return '';
    }

    /**
     * Get the nearest enclosing class-like name, or null if not inside one.
     */
    public function getClassName(): ?string
    {
        for ($i = count($this->frames) - 1; $i >= 0; $i--) {
            if ($this->frames[$i]->className !== null) {
                return $this->frames[$i]->className;
            }
        }
        return null;
    }

    /**
     * Get the nearest enclosing ClassLike AST node, or null.
     * Needed for resolving the 'parent' keyword.
     */
    public function getClassNode(): ?ClassLike
    {
        for ($i = count($this->frames) - 1; $i >= 0; $i--) {
            if ($this->frames[$i]->classNode !== null) {
                return $this->frames[$i]->classNode;
            }
        }
        return null;
    }

    /**
     * Get the SCIP symbol of the nearest enclosing method/function scope, or null.
     * Matches only ClassMethod and Function_ scopes (not closures or arrow functions),
     * consistent with TypeResolver::findEnclosingScope() which skips closures/arrows
     * when determining the caller scope for call records.
     *
     * @see getFuncLikeScope() for the variant that includes closures and arrow functions
     *
     * @return ?non-empty-string
     */
    public function getEnclosingScope(): ?string
    {
        for ($i = count($this->frames) - 1; $i >= 0; $i--) {
            $frame = $this->frames[$i];
            if ($frame->methodName !== null && ($frame->kind === 'method' || $frame->kind === 'function')) {
                return $frame->methodName;
            }
        }
        return null;
    }

    /**
     * Get the SCIP symbol of the nearest enclosing FunctionLike scope, or null.
     * Unlike getEnclosingScope(), this includes closures and arrow functions,
     * matching the behavior of SymbolNamer::funcLikeName() which walks up to
     * the nearest FunctionLike node (ClassMethod, Function_, Closure, ArrowFunction).
     *
     * @see getEnclosingScope() for the variant that excludes closures and arrow functions
     *
     * @return ?non-empty-string
     */
    public function getFuncLikeScope(): ?string
    {
        for ($i = count($this->frames) - 1; $i >= 0; $i--) {
            $frame = $this->frames[$i];
            if ($frame->methodName !== null) {
                return $frame->methodName;
            }
        }
        return null;
    }
}
