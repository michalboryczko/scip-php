<?php

declare(strict_types=1);

namespace ScipPhp\Types;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\Clone_;
use PhpParser\Node\Expr\Closure;
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
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use ScipPhp\Composer\Composer;
use ScipPhp\Indexing\ScopeStack;
use ScipPhp\Parser\DocCommentParser;
use ScipPhp\Parser\Parser;
use ScipPhp\Parser\PosResolver;
use ScipPhp\SymbolNamer;
use ScipPhp\Types\Internal\CompositeType;
use ScipPhp\Types\Internal\IterableType;
use ScipPhp\Types\Internal\NamedType;
use ScipPhp\Types\Internal\Type;
use ScipPhp\Types\Internal\TypeParser;
use ScipPhp\Types\Internal\UniformIterableType;

use function array_key_exists;
use function count;
use function in_array;
use function is_string;
use function ltrim;
use function strtolower;

final class Types
{
    private readonly Parser $parser;

    private readonly TypeParser $typeParser;

    private readonly DocCommentParser $docCommentParser;

    /** @var array<non-empty-string, array<int, non-empty-string>> */
    private array $uppers;

    /** @var array<non-empty-string, ?Type> */
    private array $defs;

    /** @var array<non-empty-string, true> */
    private array $seenDepFiles;

    /** @var array<string, list<string>> Maps callee symbol to ordered parameter names */
    private array $methodParams = [];

    /** @var array<string, array<string, Type>> scope -> varName -> Type */
    private array $localVars = [];

    /** Current scope (method/function symbol) for local variable tracking */
    private ?string $currentScope = null;

    /** Active scope stack to restore after dependency loading */
    private ?ScopeStack $activeScopeStack = null;

    public function __construct(
        private readonly Composer $composer,
        private readonly SymbolNamer $namer,
        ?Parser $parser = null,
    ) {
        $this->parser = $parser ?? new Parser();
        $this->typeParser = new TypeParser($namer);
        $this->docCommentParser = new DocCommentParser();
        $this->uppers = [];
        $this->defs = [];
        $this->seenDepFiles = [];
    }

    /**
     * Set the active scope stack to restore after dependency loading.
     */
    public function setActiveScopeStack(?ScopeStack $scopeStack): void
    {
        $this->activeScopeStack = $scopeStack;
    }

    /** @return ?non-empty-string */
    public function nameDef(Name $n): ?string
    {
        if (in_array(strtolower($n->toString()), ['null', 'true', 'false'], true)) {
            return null;
        }
        $type = $this->type($n);
        return $type?->flatten()[0] ?? null;
    }

    /**
     * @param  non-empty-string  $const
     * @return ?non-empty-string
     */
    public function constDef(Expr|Name $x, string $const): ?string
    {
        if ($const === 'class') {
            return null;
        }
        $type = $this->type($x);
        if ($type === null) {
            return null;
        }
        return $this->findDef(
            $x,
            $type->flatten(),
            fn(string $t): string => $this->namer->nameConst($t, $const),
        );
    }

    /**
     * @param  non-empty-string  $prop
     * @return ?non-empty-string
     */
    public function propDef(Expr|Name $x, string $prop): ?string
    {
        $type = $this->type($x);
        if ($type === null) {
            return null;
        }
        return $this->findDef(
            $x,
            $type->flatten(),
            fn(string $t): string => $this->namer->nameProp($t, $prop),
        );
    }

    /**
     * @param  non-empty-string  $meth
     * @return ?non-empty-string
     */
    public function methDef(Expr|Name $x, string $meth): ?string
    {
        $type = $this->type($x);
        if ($type === null) {
            return null;
        }
        return $this->findDef(
            $x,
            $type->flatten(),
            fn(string $t): string => $this->namer->nameMeth($t, $meth),
        );
    }

    /**
     * Find parent method symbols that the given method overrides.
     * Used for generating SCIP relationships with is_reference flag.
     *
     * @param  non-empty-string  $classSymbol  The class containing the method
     * @param  non-empty-string  $methodName   The method name
     * @return list<non-empty-string>          Parent method symbols that are overridden
     */
    public function getParentMethodSymbols(string $classSymbol, string $methodName): array
    {
        $parentMethods = [];
        $uppers = $this->uppers[$classSymbol] ?? [];

        foreach ($uppers as $parentSymbol) {
            // Check if parent has this method
            $parentMethodSymbol = $this->namer->nameMeth($parentSymbol, $methodName);
            if (array_key_exists($parentMethodSymbol, $this->defs)) {
                $parentMethods[] = $parentMethodSymbol;
            }

            // Recursively check grandparents
            $grandParentMethods = $this->getParentMethodSymbols($parentSymbol, $methodName);
            foreach ($grandParentMethods as $gpMethod) {
                if (!in_array($gpMethod, $parentMethods, true)) {
                    $parentMethods[] = $gpMethod;
                }
            }
        }

        return $parentMethods;
    }

    /**
     * Get the element type for a foreach loop variable.
     *
     * @param  Expr  $iterableExpr  The iterable expression being looped over
     * @return ?Type  The element type, or null if it cannot be determined
     */
    public function getForeachElementType(Expr $iterableExpr): ?Type
    {
        $type = $this->type($iterableExpr);
        if ($type instanceof IterableType) {
            return $type->valueType(null);
        }
        return null;
    }

    /**
     * Get the return type of a callback expression.
     * Handles closures, arrow functions, and callable arrays/strings.
     */
    private function getCallbackReturnType(Expr $callback): ?Type
    {
        // Arrow function: fn($x) => $x->method()
        if ($callback instanceof ArrowFunction) {
            if ($callback->returnType !== null) {
                return $this->typeParser->parse($callback->returnType);
            }
            // Try to infer from expression
            return $this->type($callback->expr);
        }

        // Closure: function($x) { return $x->method(); }
        if ($callback instanceof Closure) {
            if ($callback->returnType !== null) {
                return $this->typeParser->parse($callback->returnType);
            }
            // Could try to analyze return statements, but that's complex
            return null;
        }

        // Callable array: [$object, 'methodName'] or [ClassName::class, 'methodName']
        if ($callback instanceof Array_ && count($callback->items) === 2) {
            $first = $callback->items[0]?->value;
            $second = $callback->items[1]?->value;

            if ($second instanceof String_) {
                $methodName = $second->value;
                $classType = $this->type($first);
                if ($classType !== null) {
                    return $this->findDefType(
                        $classType,
                        $callback,
                        fn(string $t): string => $this->namer->nameMeth($t, $methodName),
                    );
                }
            }
        }

        return null;
    }

    private function type(Expr|Name $x): ?Type
    {
        if ($x instanceof ArrayDimFetch) {
            $iterType = $this->type($x->var);
            if ($iterType instanceof IterableType) {
                $key = null;
                if ($x->dim instanceof String_ || $x->dim instanceof Int_) {
                    $key = $x->dim->value;
                }
                return $iterType->valueType($key);
            }
        }

        if ($x instanceof Assign) {
            return $this->type($x->expr);
        }

        if ($x instanceof BinaryOp && $x->getOperatorSigil() === '??') {
            $leftType = $this->type($x->left);
            $rightType = $this->type($x->right);
            // For coalesce, remove null from left type, then union with right
            // If left is Foo|null and right is Bar, result is Foo|Bar
            $leftWithoutNull = CompositeType::removeNull($leftType);
            return CompositeType::union($leftWithoutNull, $rightType);
        }

        if ($x instanceof Clone_) {
            return $this->type($x->expr);
        }

        if ($x instanceof FuncCall && $x->name instanceof Name && $x->name->toString() !== '') {
            $funcName = $x->name->toString();

            // Special handling for array_map - infer return type from callback
            if ($funcName === 'array_map' && isset($x->args[0])) {
                $callbackType = $this->getCallbackReturnType($x->args[0]->value);
                if ($callbackType !== null) {
                    return new UniformIterableType($callbackType);
                }
            }

            // Special handling for array_filter - preserves input array type
            if ($funcName === 'array_filter' && isset($x->args[0])) {
                return $this->type($x->args[0]->value);
            }

            // Special handling for array_values - preserves element type
            if ($funcName === 'array_values' && isset($x->args[0])) {
                return $this->type($x->args[0]->value);
            }

            // Special handling for array_keys - returns array of keys
            if ($funcName === 'array_keys') {
                return new UniformIterableType(new NamedType('int|string'));
            }

            $name = $this->namer->name($x->name);
            if ($name === null) {
                return null;
            }
            return $this->defs[$name] ?? null;
        }

        if ($x instanceof Match_) {
            $types = [];
            foreach ($x->arms as $a) {
                $types[] = $this->type($a->body);
            }
            return CompositeType::union(...$types);
        }

        if (
            ($x instanceof MethodCall || $x instanceof NullsafeMethodCall || $x instanceof StaticCall)
            && $x->name instanceof Identifier
            && $x->name->toString() !== ''
        ) {
            $type = $x instanceof StaticCall
                ? $this->type($x->class)
                : $this->type($x->var);
            return $this->findDefType(
                $type,
                $x,
                fn(string $t): string => $this->namer->nameMeth($t, $x->name->toString()),
            );
        }

        if ($x instanceof New_) {
            if ($x->class instanceof Class_) {
                $name = $this->namer->name($x->class);
                if ($name === null) {
                    return null;
                }
                return new NamedType($name);
            }
            return $this->type($x->class);
        }

        if ($x instanceof Name) {
            $n = $this->namer->name($x);
            if ($n === null) {
                return null;
            }
            return new NamedType($n);
        }

        if (
            ($x instanceof PropertyFetch || $x instanceof NullsafePropertyFetch || $x instanceof StaticPropertyFetch)
            && $x->name instanceof Identifier
            && $x->name->toString() !== ''
        ) {
            $type = $x instanceof StaticPropertyFetch
                ? $this->type($x->class)
                : $this->type($x->var);
            return $this->findDefType(
                $type,
                $x,
                fn(string $t): string => $this->namer->nameProp($t, $x->name->toString()),
            );
        }

        if ($x instanceof Ternary) {
            $elseType = $this->type($x->else);
            if ($x->if !== null) {
                $ifType = $this->type($x->if);
                return CompositeType::union($ifType, $elseType);
            }
            $condType = $this->type($x->cond);
            return CompositeType::union($condType, $elseType);
        }

        if ($x instanceof Variable) {
            if ($x->name === 'this') {
                $name = $this->namer->nameNearestClassLike($x);
                if ($name === null) {
                    return null;
                }
                return new NamedType($name);
            }
            // Look up local variable type
            if (is_string($x->name)) {
                $type = $this->getLocalVarType($x->name);
                if ($type !== null) {
                    return $type;
                }
            }
        }

        return null;
    }

    /** @param  callable(non-empty-string): non-empty-string  $name */
    private function findDefType(?Type $t, Expr|Name $x, callable $name): ?Type
    {
        if ($t === null) {
            return null;
        }
        $name = $this->findDef($x, $t->flatten(), $name);
        return $this->defs[$name] ?? null;
    }

    /**
     * @param  list<non-empty-string>                        $types
     * @param  callable(non-empty-string): non-empty-string  $name
     * @return ?non-empty-string
     */
    private function findDef(Expr|Name $x, array $types, callable $name, int $depth = 0): ?string
    {
        // Limit recursion depth to prevent infinite loops
        if ($depth > 15) {
            return null;
        }

        foreach ($types as $t) {
            $c = $name($t);
            if (array_key_exists($c, $this->defs)) {
                return $c;
            }
        }
        foreach ($types as $t) {
            $uppers = $this->uppers[$t] ?? [];
            $c = $this->findDef($x, $uppers, $name, $depth + 1);
            if ($c !== null) {
                return $c;
            }
        }
        // Try loading external dependencies and check their inheritance chains
        foreach ($types as $t) {
            $ident = $this->namer->extractIdent($t);
            if (!$this->composer->isDependency($ident)) {
                continue;
            }
            $f = $this->composer->findFile($ident);
            if ($f === null) {
                continue;
            }
            if (isset($this->seenDepFiles[$f])) {
                continue;
            }
            // Temporarily suspend scope stack during dependency loading
            // to prevent stale scope context from the current file
            $this->namer->setScopeStack(null);
            $this->parser->traverse($f, $this, $this->collectDefs(...));
            $this->namer->setScopeStack($this->activeScopeStack);
            $this->seenDepFiles[$f] = true;

            // After loading, first check if the symbol is now directly available
            $c = $name($t);
            if (array_key_exists($c, $this->defs)) {
                return $c;
            }

            // Then check if we can resolve via uppers (inheritance chain)
            $uppers = $this->uppers[$t] ?? [];
            if (!empty($uppers)) {
                // Recursively try to find def in parent classes
                // This handles cases like TestCase -> Assert where assertSame is in Assert
                $c = $this->findDef($x, $uppers, $name, $depth + 1);
                if ($c !== null) {
                    return $c;
                }
            }
        }
        return null;
    }

    /** @param  non-empty-string  $filenames */
    public function collect(string ...$filenames): void
    {
        foreach ($filenames as $f) {
            try {
                $this->parser->traverse($f, $this, $this->collectDefs(...));
            } catch (\Throwable $e) {
                fwrite(STDERR, "Warning: skipping type collection for {$f}: {$e->getMessage()}\n");
            }
        }
    }

    /**
     * Set the current scope for local variable tracking.
     *
     * @param ?string $scope The method/function symbol, or null to clear
     */
    public function setCurrentScope(?string $scope): void
    {
        $this->currentScope = $scope;
        if ($scope !== null && !isset($this->localVars[$scope])) {
            $this->localVars[$scope] = [];
        }
    }

    /**
     * Get the current scope.
     */
    public function getCurrentScope(): ?string
    {
        return $this->currentScope;
    }

    /**
     * Register a local variable with its type.
     *
     * @param string $varName The variable name (without $)
     * @param Expr $expr The expression assigned to the variable
     * @return ?Type The resolved type, or null if unknown
     */
    public function registerLocalVar(string $varName, Expr $expr): ?Type
    {
        if ($this->currentScope === null) {
            return null;
        }
        $type = $this->type($expr);
        if ($type !== null) {
            $this->localVars[$this->currentScope][$varName] = $type;
        }
        return $type;
    }

    /**
     * Register a local variable with a pre-computed type.
     * Used for foreach loop variables where the type is derived from the iterable.
     *
     * @param string $varName The variable name (without $)
     * @param ?Type $type The type to register, or null if unknown
     */
    public function registerLocalVarWithType(string $varName, ?Type $type): void
    {
        if ($this->currentScope === null || $type === null) {
            return;
        }
        $this->localVars[$this->currentScope][$varName] = $type;
    }

    /**
     * Get the type of a local variable.
     *
     * @param string $varName The variable name (without $)
     * @return ?Type The type, or null if unknown
     */
    public function getLocalVarType(string $varName): ?Type
    {
        if ($this->currentScope === null) {
            return null;
        }
        return $this->localVars[$this->currentScope][$varName] ?? null;
    }

    /**
     * Get the return type of a method/function by its symbol.
     *
     * @param  string  $symbol  The callee SCIP symbol
     * @return ?Type   The return type, or null if unknown
     */
    public function getReturnType(string $symbol): ?Type
    {
        return $this->defs[$symbol] ?? null;
    }

    /**
     * Get the type of an expression.
     *
     * @param  Expr|Name  $expr  The expression to type
     * @return ?Type      The type, or null if unknown
     */
    public function getExprType(Node $expr): ?Type
    {
        if ($expr instanceof Expr || $expr instanceof Name) {
            return $this->type($expr);
        }
        return null;
    }

    /**
     * Clear local variables for a scope.
     */
    public function clearScope(?string $scope = null): void
    {
        $scope ??= $this->currentScope;
        if ($scope !== null) {
            unset($this->localVars[$scope]);
        }
    }

    /**
     * Get ordered parameter names for a callee symbol.
     *
     * @param  string  $symbol  The callee SCIP symbol
     * @return ?list<string>    Ordered parameter names, or null if unavailable
     */
    public function getMethodParams(string $symbol): ?array
    {
        return $this->methodParams[$symbol] ?? null;
    }

    /**
     * Parse a type node into a Type object.
     * Public wrapper for TypeParser::parse() to allow DocIndexer to parse parameter types.
     *
     * @param  Node|null  $typeNode  The type node (Name, Identifier, NullableType, UnionType, etc.)
     * @return ?Type      The parsed type, or null if unparseable
     */
    public function parseType(?Node $typeNode): ?Type
    {
        return $this->typeParser->parse($typeNode);
    }

    private function collectDefs(PosResolver $pos, Node $n): void // phpcs:ignore
    {
        if ($n instanceof ClassConst) {
            foreach ($n->consts as $c) {
                $name = $this->namer->name($c);
                if ($name !== null) {
                    $this->defs[$name] = null;
                }
            }
        } elseif ($n instanceof ClassLike) {
            $this->collectUppers($n);
            $name = $this->namer->name($n);
            if ($name !== null) {
                $this->defs[$name] = new NamedType($name);
            }
        } elseif ($n instanceof EnumCase) {
            $name = $this->namer->name($n);
            if ($name !== null) {
                $this->defs[$name] = null;
            }
        } elseif ($n instanceof FunctionLike) {
            $name = $this->namer->name($n);
            if ($name !== null) {
                $type = $this->typeParser->parse($n->getReturnType());
                if ($type === null) {
                    $t = $this->docCommentParser->parseReturnType($n);
                    $type = $this->typeParser->parseDoc($n, $t);
                }
                $this->defs[$name] = $type;

                // Collect parameter names for call tracking
                $paramNames = [];
                foreach ($n->getParams() as $param) {
                    if ($param->var instanceof Variable && is_string($param->var->name)) {
                        $paramNames[] = $param->var->name;
                    }
                }
                if ($paramNames !== []) {
                    $this->methodParams[$name] = $paramNames;
                }
            }
        } elseif ($n instanceof Param && $n->var instanceof Variable && is_string($n->var->name)) {
            // Constructor property promotion.
            if ($n->flags !== 0) {
                // Get class symbol from context by walking up the parent chain
                $parent = $n->getAttribute('parent');  // ClassMethod __construct
                $classLike = $parent?->getAttribute('parent');  // ClassLike
                if ($classLike instanceof ClassLike) {
                    $classSymbol = $this->namer->name($classLike);
                    if ($classSymbol !== null) {
                        $propSymbol = $this->namer->nameProp($classSymbol, $n->var->name);
                        $type = $this->typeParser->parse($n->type);
                        $this->defs[$propSymbol] = $type;
                    }
                }
            }
        } elseif ($n instanceof Property) {
            foreach ($n->props as $p) {
                $name = $this->namer->name($p);
                if ($name === null) {
                    continue;
                }

                $type = $this->typeParser->parse($n->type);
                if ($type === null) {
                    $t = $this->docCommentParser->parsePropertyType($n);
                    $type = $this->typeParser->parseDoc($n, $t);
                }
                $this->defs[$name] = $type;
            }
        }
    }

    private function collectUppers(ClassLike $c): void
    {
        $name = $this->namer->name($c);
        if ($name === null) {
            return;
        }

        foreach ($c->getTraitUses() as $use) {
            foreach ($use->traits as $t) {
                $this->addUpper($name, $t);
            }
        }
        if ($c instanceof Class_) {
            if ($c->extends !== null) {
                $this->addUpper($name, $c->extends);
            }
            foreach ($c->implements as $i) {
                $this->addUpper($name, $i);
            }
        } elseif ($c instanceof Interface_) {
            foreach ($c->extends as $i) {
                $this->addUpper($name, $i);
            }
        }

        $props = $this->docCommentParser->parseProperties($c);
        foreach ($props as $p) {
            $propName = ltrim($p->propertyName, '$');
            if ($propName === '') {
                continue;
            }
            $propName = $this->namer->nameProp($name, $propName);
            $type = $this->typeParser->parseDoc($c, $p->type);
            $this->defs[$propName] = $type;
        }

        $methods = $this->docCommentParser->parseMethods($c);
        foreach ($methods as $m) {
            if ($m->methodName === '') {
                continue;
            }
            $methName = $this->namer->nameMeth($name, $m->methodName);
            $type = $this->typeParser->parseDoc($c, $m->returnType);
            $this->defs[$methName] = $type;
        }
    }

    /** @param  non-empty-string  $c */
    private function addUpper(string $c, Name $upper): void
    {
        $name = $this->namer->name($upper);
        if ($name === null) {
            return;
        }
        if (!isset($this->uppers[$c])) {
            $this->uppers[$c] = [];
        }
        $this->uppers[$c][] = $name;
        $this->defs[$name] = new NamedType($name);
    }
}
