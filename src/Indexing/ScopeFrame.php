<?php

declare(strict_types=1);

namespace ScipPhp\Indexing;

use PhpParser\Node\Stmt\ClassLike;

final readonly class ScopeFrame
{
    /**
     * @param  string              $namespace   Namespace prefix (e.g., "App/Models/") or empty string
     * @param  ?non-empty-string   $className   Class-like name or null if not inside a class
     * @param  ?ClassLike          $classNode   The ClassLike AST node (needed for 'parent' keyword resolution)
     * @param  ?non-empty-string   $methodName  SCIP symbol of the enclosing method/function, or null
     * @param  string              $kind        One of: 'namespace', 'class', 'method', 'function', 'closure', 'arrow'
     */
    public function __construct(
        public string $namespace,
        public ?string $className,
        public ?ClassLike $classNode,
        public ?string $methodName,
        public string $kind,
    ) {
    }
}
