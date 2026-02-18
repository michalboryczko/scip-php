<?php

declare(strict_types=1);

namespace ScipPhp\Indexing;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use ScipPhp\SymbolNamer;
use ScipPhp\Types\Internal\Type;
use ScipPhp\Types\Types;

use function count;
use function implode;
use function in_array;
use function str_starts_with;

final class TypeResolver
{
    public function __construct(
        private readonly SymbolNamer $namer,
        private readonly Types $types,
    ) {
    }

    /**
     * Format a Type as a human-readable string.
     *
     * For builtin types, uses short names (int, string, null, etc.)
     * For class types, keeps the full SCIP symbol (for reference linking)
     * Union types are joined with '|'
     *
     * @return string  The formatted type string, or 'mixed' if null
     */
    public function formatTypeForDoc(?Type $type): string
    {
        if ($type === null) {
            return 'mixed';
        }

        $flat = $type->flatten();
        if (count($flat) === 0) {
            return 'mixed';
        }

        $formattedTypes = [];
        foreach ($flat as $symbol) {
            // For builtin types, use short names
            if (str_starts_with($symbol, 'scip-php php builtin . ')) {
                $formattedTypes[] = $this->namer->extractShortTypeName($symbol);
            } else {
                // For class types, keep the full symbol
                $formattedTypes[] = $symbol;
            }
        }

        return implode('|', $formattedTypes);
    }

    /**
     * Convert a Type to a symbol string for ValueRecord.
     */
    public function formatTypeSymbol(?Type $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $flat = $type->flatten();
        if (count($flat) === 0) {
            return null;
        }

        if (count($flat) === 1) {
            return $flat[0];
        }

        return $this->namer->nameUnion($flat);
    }

    /**
     * Resolve the return type for a value-producing expression.
     * For nullsafe operations, the return type is a union with null.
     *
     * @param  Node\Expr  $expr  The expression node
     * @return ?string    The return type symbol, or null if unknown
     */
    public function resolveExpressionReturnType(Node\Expr $expr): ?string
    {
        // Check if this is a nullsafe operation (requires union with null)
        $isNullsafe = $expr instanceof NullsafePropertyFetch
                   || $expr instanceof NullsafeMethodCall;

        $type = $this->types->getExprType($expr);
        if ($type === null) {
            // For nullsafe without resolved type, return null-only type
            if ($isNullsafe) {
                return 'scip-php php builtin . null#';
            }
            return null;
        }

        $flat = $type->flatten();
        if (count($flat) === 0) {
            if ($isNullsafe) {
                return 'scip-php php builtin . null#';
            }
            return null;
        }

        return $this->applyNullsafeUnion($flat, $isNullsafe);
    }

    /**
     * Resolve return type for property/array access expressions.
     *
     * For property accesses (access, access_static), uses the callee symbol
     * to look up the property type directly from Types::defs.
     * For nullsafe operations, adds null to create a union type.
     *
     * @param  Node\Expr  $expr    The expression node
     * @param  ?string    $symbol  The callee symbol (property symbol for property accesses)
     * @return ?string    The return type symbol, or null if unknown
     */
    public function resolveAccessReturnType(Node\Expr $expr, ?string $symbol): ?string
    {
        // Check if this is a nullsafe operation (requires union with null)
        $isNullsafe = $expr instanceof NullsafePropertyFetch
                   || $expr instanceof NullsafeMethodCall;

        // For property accesses with a known symbol, look up the property type directly
        if ($symbol !== null && ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch || $expr instanceof StaticPropertyFetch)) {
            // Look up the property type from Types::defs using the callee symbol
            $type = $this->types->getReturnType($symbol);

            if ($type === null) {
                // Property type not found - for nullsafe, return null-only type
                if ($isNullsafe) {
                    return 'scip-php php builtin . null#';
                }
                return null;
            }

            $flat = $type->flatten();
            if (count($flat) === 0) {
                if ($isNullsafe) {
                    return 'scip-php php builtin . null#';
                }
                return null;
            }

            return $this->applyNullsafeUnion($flat, $isNullsafe);
        }

        // For other expressions (array access, operators), use the original method
        return $this->resolveExpressionReturnType($expr);
    }

    /**
     * Determine the call kind based on the node type.
     *
     * @param  Node  $callNode  The call expression node
     * @return string  The call kind
     */
    public function resolveCallKind(Node $callNode): string
    {
        // Method/function/constructor calls (invocations)
        if ($callNode instanceof New_) {
            return 'constructor';
        }
        if ($callNode instanceof FuncCall) {
            return 'function';
        }
        if ($callNode instanceof StaticCall) {
            return 'method_static';
        }
        if ($callNode instanceof NullsafeMethodCall) {
            // Nullsafe is expressed via union return type, not separate kind
            return 'method';
        }
        if ($callNode instanceof MethodCall) {
            return 'method';
        }

        // Property access (v3: property -> access)
        // Nullsafe is expressed via union return type, not separate kind
        if ($callNode instanceof NullsafePropertyFetch) {
            return 'access';
        }
        if ($callNode instanceof StaticPropertyFetch) {
            return 'access_static';
        }
        if ($callNode instanceof PropertyFetch) {
            return 'access';
        }

        // Array access
        if ($callNode instanceof ArrayDimFetch) {
            return 'access_array';
        }

        // Operators
        if ($callNode instanceof Coalesce) {
            return 'coalesce';
        }
        if ($callNode instanceof Ternary) {
            // Elvis operator: $a ?: $b (if is null)
            // Full ternary: $a ? $b : $c (if is not null)
            return $callNode->if === null ? 'ternary' : 'ternary_full';
        }
        if ($callNode instanceof Match_) {
            return 'match';
        }

        // Value kinds (these should be handled by ValueRecords, but kept for safety)
        if ($callNode instanceof Variable) {
            return 'local';  // Will be handled by ValueRecord
        }
        if ($callNode instanceof ClassConstFetch) {
            return 'constant';  // Will be handled by ValueRecord
        }
        if ($callNode instanceof ConstFetch) {
            return 'constant';  // Will be handled by ValueRecord
        }

        // Literal values
        if ($callNode instanceof Scalar) {
            return 'literal';
        }
        if ($callNode instanceof Node\Expr\Array_) {
            return 'literal';
        }

        // Fallback for unexpected node types
        return 'method';
    }

    /**
     * Determine the kind_type category based on the kind.
     *
     * @param  string  $kind  The call kind
     * @return string  The kind type: invocation, access, or operator
     */
    public function resolveKindType(string $kind): string
    {
        return match ($kind) {
            // Invocations
            'method', 'method_static', 'function', 'constructor' => 'invocation',
            // Access
            'access', 'access_static', 'access_array' => 'access',
            // Operators
            'coalesce', 'ternary', 'ternary_full', 'match' => 'operator',
            // Fallback
            default => 'invocation',
        };
    }

    /**
     * Resolve the return type for a call expression.
     *
     * For constructors: returns the class symbol
     * For method/function calls: returns the declared return type symbol
     * For nullsafe operations: returns union type T|null
     *
     * @param  Node             $callNode      The call expression node
     * @param  non-empty-string $calleeSymbol  SCIP symbol of the callee
     * @return ?string  The return type symbol, or null if unknown
     */
    public function resolveReturnType(Node $callNode, string $calleeSymbol): ?string
    {
        // Check if this is a nullsafe operation (requires union with null)
        $isNullsafe = $callNode instanceof NullsafePropertyFetch
                   || $callNode instanceof NullsafeMethodCall;

        // For constructor calls, the return type is the class itself
        if ($callNode instanceof New_) {
            if ($callNode->class instanceof Name) {
                return $this->types->nameDef($callNode->class);
            }
            return null;
        }

        // For method and function calls, look up the return type from defs
        // The callee symbol points to the method, and its type is stored in defs
        $type = $this->types->getReturnType($calleeSymbol);
        if ($type === null) {
            // For nullsafe without resolved type, return null-only type
            if ($isNullsafe) {
                return 'scip-php php builtin . null#';
            }
            return null;
        }

        // Convert Type to a symbol string
        $flat = $type->flatten();
        if (count($flat) === 0) {
            if ($isNullsafe) {
                return 'scip-php php builtin . null#';
            }
            return null;
        }

        return $this->applyNullsafeUnion($flat, $isNullsafe);
    }

    /**
     * Resolve an argument expression to a type symbol.
     *
     * @param  Node\Expr  $expr  The argument expression
     * @return ?string  The type symbol, or null if unknown
     */
    public function resolveValueType(Node\Expr $expr): ?string
    {
        $type = $this->types->getExprType($expr);
        if ($type === null) {
            return null;
        }

        $flat = $type->flatten();
        if (count($flat) === 0) {
            return null;
        }

        if (count($flat) === 1) {
            return $flat[0];
        }

        // Multiple types - create a union symbol
        return $this->namer->nameUnion($flat);
    }

    /**
     * Find the enclosing method/function scope for a node.
     *
     * @return ?non-empty-string The scope symbol, or null if not in a scope
     */
    public function findEnclosingScope(Node $n): ?string
    {
        $parent = $n->getAttribute('parent');

        while ($parent !== null) {
            if ($parent instanceof ClassMethod || $parent instanceof Function_) {
                return $this->namer->name($parent);
            }
            $parent = $parent->getAttribute('parent');
        }

        return null;
    }

    /**
     * Apply nullsafe union logic to a flattened type array.
     *
     * If isNullsafe is true, adds null to the union. Otherwise returns
     * the single type or a union symbol.
     *
     * @param  list<non-empty-string>  $flat        Flattened type symbols
     * @param  bool                    $isNullsafe  Whether to add null to union
     * @return ?string  The type symbol or union symbol
     */
    private function applyNullsafeUnion(array $flat, bool $isNullsafe): ?string
    {
        if ($isNullsafe) {
            $nullSymbol = 'scip-php php builtin . null#';
            if (!in_array($nullSymbol, $flat, true)) {
                $flat[] = $nullSymbol;
            }
            return $this->namer->nameUnion($flat);
        }

        if (count($flat) === 1) {
            return $flat[0];
        }

        return $this->namer->nameUnion($flat);
    }
}
