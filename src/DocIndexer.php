<?php

declare(strict_types=1);

namespace ScipPhp;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp;
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
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;
use Scip\Occurrence;
use Scip\Relationship;
use Scip\SymbolInformation;
use Scip\SymbolRole;
use Scip\SyntaxKind;
use ScipPhp\Calls\CallRecord;
use ScipPhp\Calls\ValueRecord;
use ScipPhp\Composer\Composer;
use ScipPhp\Indexing\CallRecordBuilder;
use ScipPhp\Indexing\IndexingContext;
use ScipPhp\Indexing\TypeResolver;
use ScipPhp\Parser\DocCommentParser;
use ScipPhp\Parser\PosResolver;
use ScipPhp\Types\Types;

use function array_merge;
use function array_slice;
use function explode;
use function implode;
use function in_array;
use function is_int;
use function is_string;
use function ltrim;
use function spl_object_id;
use function str_starts_with;
use function strtolower;

final class DocIndexer
{
    private readonly IndexingContext $ctx;

    private readonly TypeResolver $typeResolver;

    private readonly CallRecordBuilder $callBuilder;

    private readonly DocGenerator $docGenerator;

    private readonly DocCommentParser $docCommentParser;

    public function __construct(
        private readonly Composer $composer,
        private readonly SymbolNamer $namer,
        private readonly Types $types,
        string $relativePath = '',
        bool $experimental = false,
    ) {
        $this->ctx = new IndexingContext($relativePath, $experimental);
        $this->typeResolver = new TypeResolver($namer, $types);
        $this->callBuilder = new CallRecordBuilder($this->ctx, $this->typeResolver, $namer, $types);
        $this->docGenerator = new DocGenerator();
        $this->docCommentParser = new DocCommentParser();
    }

    public function getContext(): IndexingContext
    {
        return $this->ctx;
    }

    public function index(PosResolver $pos, Node $n): void
    {
        // ------- Definitions -------

        if ($n instanceof ClassConst) {
            foreach ($n->consts as $c) {
                $this->def($pos, $c, $c->name, SyntaxKind::IdentifierConstant);
            }
            return;
        }
        if ($n instanceof ClassLike && $n->name !== null) {
            $this->def($pos, $n, $n->name, SyntaxKind::IdentifierType);
            $name = $this->namer->name($n);
            if ($name === null) {
                return;
            }

            $props = $this->docCommentParser->parseProperties($n);
            foreach ($props as $p) {
                $propName = ltrim($p->propertyName, '$');
                if ($p->propertyName === '' || $propName === '') {
                    continue;
                }
                $symbol = $this->namer->nameProp($name, $propName);
                $this->docDef($n->getDocComment(), '@property', $p, $p->propertyName, $symbol);
            }

            $methods = $this->docCommentParser->parseMethods($n);
            foreach ($methods as $m) {
                if ($m->methodName === '') {
                    continue;
                }
                $symbol = $this->namer->nameMeth($name, $m->methodName);
                $this->docDef($n->getDocComment(), '@method', $m, $m->methodName, $symbol);
            }
            return;
        }
        if ($n instanceof ClassMethod) {
            $this->def($pos, $n, $n->name);
            return;
        }
        if ($n instanceof EnumCase) {
            $this->def($pos, $n, $n->name, SyntaxKind::IdentifierConstant);
            return;
        }
        if ($n instanceof Function_) {
            $this->def($pos, $n, $n->name, SyntaxKind::IdentifierFunctionDefinition);
            return;
        }
        if ($n instanceof Param && $n->var instanceof Variable && is_string($n->var->name)) {
            // Constructor property promotion.
            if ($n->flags !== 0) {
                $p = new PropertyItem($n->var->name, $n->default, $n->getAttributes());
                $prop = new Property($n->flags, [$p], $n->getAttributes(), $n->type, $n->attrGroups);
                $p->setAttribute('parent', $prop);
                $this->def($pos, $p, $n->var, SyntaxKind::IdentifierParameter);
                // Register promoted property parameter type for resolution
                $this->registerParameterType($n);
                // Resolve the promoted Property symbol for the assigned_from edge
                $promotedPropertySymbol = $this->namer->name($p);
                // Create ValueRecord at declaration site (One Value Per Declaration Rule)
                $this->createParameterValueRecord($pos, $n, $promotedPropertySymbol);
                return;
            }
            $this->def($pos, $n, $n->var, SyntaxKind::IdentifierParameter);
            // Register parameter type for type resolution in expressions
            $this->registerParameterType($n);
            // Create ValueRecord at declaration site (One Value Per Declaration Rule)
            $this->createParameterValueRecord($pos, $n);
            return;
        }
        if ($n instanceof Property) {
            foreach ($n->props as $p) {
                // Set parent attribute so we can access the type in def()
                $p->setAttribute('parent', $n);
                $this->def($pos, $p, $p->name);
            }
            return;
        }

        // ------- Foreach Loop Variables -------

        if ($n instanceof Foreach_ && $n->valueVar instanceof Variable && is_string($n->valueVar->name)) {
            $this->handleForeachVariable($pos, $n);
            return;
        }

        // ------- Local Variables -------

        if ($n instanceof Assign && $n->var instanceof Variable && is_string($n->var->name)) {
            $this->handleLocalVariable($pos, $n);
            return;
        }

        // Handle variable reads (not part of assignment LHS)
        if ($n instanceof Variable && is_string($n->name) && $n->name !== 'this') {
            $parent = $n->getAttribute('parent');
            // Skip if this variable is the LHS of an assignment (already handled above)
            if (!($parent instanceof Assign && $parent->var === $n)) {
                $this->handleLocalVariableRef($pos, $n);
            }
            return;
        }

        // ------- Usages -------

        if ($n instanceof ClassConstFetch && $n->name instanceof Identifier && $n->name->toString() !== '') {
            $symbol = $this->types->constDef($n->class, $n->name->toString());
            if ($symbol !== null) {
                $this->ref($pos, $symbol, $n->name, SyntaxKind::IdentifierConstant);
            }
            return;
        }
        if (
            ($n instanceof MethodCall || $n instanceof NullsafeMethodCall || $n instanceof StaticCall)
            && $n->name instanceof Identifier
            && $n->name->toString() !== ''
        ) {
            $symbol = $n instanceof StaticCall
                ? $this->types->methDef($n->class, $n->name->toString())
                : $this->types->methDef($n->var, $n->name->toString());
            if ($symbol !== null) {
                $this->ref($pos, $symbol, $n->name);

                // Call tracking (skip first-class callables like $obj->method(...))
                if (!$n->isFirstClassCallable()) {
                    $callRecord = $this->callBuilder->buildCallRecord($pos, $n, $symbol, $n->getArgs(), $this->trackExpression(...));
                    if ($callRecord !== null) {
                        $this->callBuilder->addCallWithResultValue($callRecord);
                    }
                }
            }
            return;
        }
        if ($n instanceof FuncCall && $n->name instanceof Name) {
            $symbol = $this->types->nameDef($n->name);
            if ($symbol !== null) {
                // Function calls are experimental - only track with --experimental flag
                if ($this->ctx->experimental && !$n->isFirstClassCallable()) {
                    $callRecord = $this->callBuilder->buildCallRecord($pos, $n, $symbol, $n->getArgs(), $this->trackExpression(...));
                    if ($callRecord !== null) {
                        $this->callBuilder->addCallWithResultValue($callRecord);
                    }
                }
            }
            return;
        }
        if ($n instanceof New_ && $n->class instanceof Name) {
            $classSymbol = $this->types->nameDef($n->class);
            if ($classSymbol !== null) {
                // Constructor callee: ClassName#__construct().
                $calleeSymbol = $this->namer->nameMeth($classSymbol, '__construct');
                $callRecord = $this->callBuilder->buildCallRecord($pos, $n, $calleeSymbol, $n->getArgs(), $this->trackExpression(...));
                if ($callRecord !== null) {
                    $this->callBuilder->addCallWithResultValue($callRecord);
                }
            }
            return;
        }
        if ($n instanceof Name) {
            if ($n->getAttribute('parent') instanceof Namespace_) {
                return;
            }
            $symbol = $this->types->nameDef($n);
            if ($symbol !== null) {
                $this->ref($pos, $symbol, $n);
            }
            return;
        }
        if (
            ($n instanceof PropertyFetch || $n instanceof NullsafePropertyFetch || $n instanceof StaticPropertyFetch)
            && $n->name instanceof Identifier
            && $n->name->toString() !== ''
        ) {
            $symbol = $n instanceof StaticPropertyFetch
                ? $this->types->propDef($n->class, $n->name->toString())
                : $this->types->propDef($n->var, $n->name->toString());
            if ($symbol !== null) {
                $this->ref($pos, $symbol, $n->name);

                // Call tracking for property access
                // Track receiver (for non-static property access)
                $receiverValueId = null;
                if (!($n instanceof StaticPropertyFetch)) {
                    $receiverValueId = $this->trackExpression($pos, $n->var);
                }

                $callRecord = $this->callBuilder->buildAccessOrOperatorCallRecord(
                    pos: $pos,
                    exprNode: $n,
                    symbol: $symbol,
                    receiverValueId: $receiverValueId,
                    positionNode: $n->name,
                );
                if ($callRecord !== null) {
                    $this->callBuilder->addCallWithResultValue($callRecord);
                }
            }
            return;
        }

        // ------- Operator and Access Expression Tracking -------
        // These expressions need to be tracked directly when they appear
        // as standalone expressions (e.g., in return statements).

        if ($n instanceof ArrayDimFetch) {
            // Track array access: $arr[$key]
            $this->trackArrayDimFetchExpression($pos, $n);
            return;
        }
        if ($n instanceof Coalesce) {
            // Track null coalesce: $a ?? $b
            $this->trackCoalesceExpression($pos, $n);
            return;
        }
        if ($n instanceof Ternary) {
            // Track ternary: $a ? $b : $c or $a ?: $b
            $this->trackTernaryExpression($pos, $n);
            return;
        }
        if ($n instanceof Match_) {
            // Track match expression: match($x) { ... }
            $this->trackMatchExpression($pos, $n);
            return;
        }
    }

    private function def(
        PosResolver $pos,
        Const_|ClassLike|ClassMethod|EnumCase|Function_|Param|PropertyItem $n,
        Node $posNode,
        int $kind = SyntaxKind::Identifier,
    ): void {
        $symbol = $this->namer->name($n);
        if ($symbol === null) {
            return;
        }
        if (!mb_check_encoding($symbol, 'UTF-8')) {
            return;
        }
        $doc = $this->docGenerator->create($n);
        $this->ctx->symbols[$symbol] = new SymbolInformation([
            'symbol'        => $symbol,
            'documentation' => $doc,
        ]);

        $occurrence = new Occurrence([
            'range'        => $pos->pos($posNode),
            'symbol'       => $symbol,
            'symbol_roles' => SymbolRole::Definition,
            'syntax_kind'  => $kind,
        ]);

        // For ClassLike, ClassMethod, and Function_, set enclosing_range to the full body extent.
        // This allows downstream tools to determine symbol containment.
        if ($n instanceof ClassLike || $n instanceof ClassMethod || $n instanceof Function_) {
            $occurrence->setEnclosingRange($pos->pos($n));
        }

        $this->ctx->occurrences[] = $occurrence;

        // Add relationships for class-like definitions
        if ($n instanceof ClassLike) {
            $relationships = $this->extractRelationships($n);
            if (!empty($relationships)) {
                $this->ctx->symbols[$symbol]->setRelationships($relationships);
            }
        }

        // Add relationships for method overrides
        if ($n instanceof ClassMethod) {
            $relationships = $this->extractMethodRelationships($n, $symbol);
            // Also add type definition relationships for return type
            $returnTypeRelationships = $this->extractReturnTypeRelationships($n);
            $relationships = array_merge($relationships, $returnTypeRelationships);
            if (!empty($relationships)) {
                $this->ctx->symbols[$symbol]->setRelationships($relationships);
            }
        }

        // Add type definition relationships for parameters
        if ($n instanceof Param && $n->type !== null) {
            $typeRelationships = $this->extractTypeRelationships($n->type);
            if (!empty($typeRelationships)) {
                $this->ctx->symbols[$symbol]->setRelationships($typeRelationships);
            }
        }

        // Add type definition relationships for properties
        if ($n instanceof PropertyItem) {
            $parent = $n->getAttribute('parent');
            if ($parent instanceof Property && $parent->type !== null) {
                $typeRelationships = $this->extractTypeRelationships($parent->type);
                if (!empty($typeRelationships)) {
                    $this->ctx->symbols[$symbol]->setRelationships($typeRelationships);
                }
            }
        }
    }

    /**
     * Extract relationships (extends, implements, uses trait) from a ClassLike node.
     *
     * Uses different flags to distinguish relationship types:
     * - extends (class or interface): is_reference: true
     * - implements (interface): is_implementation: true
     * - uses (trait): is_reference: true, is_implementation: true
     *
     * @return list<Relationship>
     */
    private function extractRelationships(ClassLike $n): array
    {
        $relationships = [];

        // Handle trait uses (Class, Trait can use traits)
        // Use both is_reference and is_implementation for traits (code composition)
        foreach ($n->getTraitUses() as $traitUse) {
            foreach ($traitUse->traits as $trait) {
                $traitSymbol = $this->types->nameDef($trait);
                if ($traitSymbol !== null) {
                    $relationships[] = new Relationship([
                        'symbol'            => $traitSymbol,
                        'is_reference'      => true,
                        'is_implementation' => true,
                    ]);
                }
            }
        }

        if ($n instanceof Class_) {
            // Handle extends (single parent class) - use is_reference for parent class reference
            if ($n->extends !== null) {
                $parentSymbol = $this->types->nameDef($n->extends);
                if ($parentSymbol !== null) {
                    $relationships[] = new Relationship([
                        'symbol'       => $parentSymbol,
                        'is_reference' => true,
                    ]);
                }
            }

            // Handle implements (multiple interfaces) - use is_implementation for interface implementation
            foreach ($n->implements as $interface) {
                $interfaceSymbol = $this->types->nameDef($interface);
                if ($interfaceSymbol !== null) {
                    $relationships[] = new Relationship([
                        'symbol'            => $interfaceSymbol,
                        'is_implementation' => true,
                    ]);
                }
            }
        }

        if ($n instanceof Interface_) {
            // Handle extends (multiple parent interfaces) - use is_reference for interface extension
            foreach ($n->extends as $parentInterface) {
                $parentSymbol = $this->types->nameDef($parentInterface);
                if ($parentSymbol !== null) {
                    $relationships[] = new Relationship([
                        'symbol'       => $parentSymbol,
                        'is_reference' => true,
                    ]);
                }
            }
        }

        // Trait definitions can also extend interfaces (rare but possible via abstract methods)
        // Trait_ doesn't have extends or implements, but can use other traits (handled above)

        return $relationships;
    }

    /**
     * Extract method override relationships for a ClassMethod node.
     *
     * @param  non-empty-string  $methodSymbol
     * @return list<Relationship>
     */
    private function extractMethodRelationships(ClassMethod $n, string $methodSymbol): array
    {
        $relationships = [];

        // Find the containing class
        $parent = $n->getAttribute('parent');
        if (!($parent instanceof ClassLike)) {
            return $relationships;
        }

        $classSymbol = $this->namer->name($parent);
        if ($classSymbol === null) {
            return $relationships;
        }

        $methodName = $n->name->toString();
        if ($methodName === '') {
            return $relationships;
        }

        // Find parent methods that this method overrides
        $parentMethods = $this->types->getParentMethodSymbols($classSymbol, $methodName);

        foreach ($parentMethods as $parentMethodSymbol) {
            $relationships[] = new Relationship([
                'symbol'            => $parentMethodSymbol,
                'is_implementation' => true,
                'is_reference'      => true,
            ]);
        }

        return $relationships;
    }

    /**
     * Extract type definition relationships from a return type.
     *
     * @return list<Relationship>
     */
    private function extractReturnTypeRelationships(ClassMethod|Function_ $n): array
    {
        if ($n->returnType === null) {
            return [];
        }

        return $this->extractTypeRelationships($n->returnType);
    }

    /**
     * Extract type definition relationships from a type node.
     * Handles Name, NullableType, UnionType, and IntersectionType.
     *
     * For composite types (union/intersection), creates a synthetic type symbol
     * and returns a single relationship pointing to that synthetic type.
     *
     * @param Node $type The type node (Name, NullableType, UnionType, IntersectionType, etc.)
     * @return list<Relationship>
     */
    private function extractTypeRelationships(Node $type): array
    {
        // Handle nullable types (?Foo) - emit relationship to the inner type only
        // (null is a builtin and not emitted to SCIP)
        if ($type instanceof NullableType) {
            return $this->extractTypeRelationships($type->type);
        }

        // Handle union types (Foo|Bar)
        // For SCIP output, we only emit relationships to class/interface symbols (not synthetics)
        if ($type instanceof UnionType) {
            $relationships = [];
            foreach ($type->types as $subType) {
                $subRelationships = $this->extractTypeRelationships($subType);
                foreach ($subRelationships as $r) {
                    $relationships[] = $r;
                }
            }
            return $relationships;
        }

        // Handle intersection types (Foo&Bar)
        // For SCIP output, we only emit relationships to class/interface symbols
        if ($type instanceof IntersectionType) {
            $relationships = [];
            foreach ($type->types as $subType) {
                $subRelationships = $this->extractTypeRelationships($subType);
                foreach ($subRelationships as $r) {
                    $relationships[] = $r;
                }
            }
            return $relationships;
        }

        // Handle Name nodes (class/interface references)
        if ($type instanceof Name) {
            // Skip self/static/parent - these are contextual and resolve to the
            // current class, which doesn't add meaningful type relationship info
            $nameStr = strtolower($type->toString());
            if (in_array($nameStr, ['self', 'static', 'parent'], true)) {
                return [];
            }

            $typeSymbol = $this->resolveNameToSymbol($type);
            // Only emit relationships to non-synthetic symbols (regular classes)
            // Skip builtin types as they're not valid SCIP symbols
            if ($typeSymbol !== null && str_starts_with($typeSymbol, 'scip-php composer ')) {
                return [new Relationship([
                    'symbol'             => $typeSymbol,
                    'is_type_definition' => true,
                ])];
            }
        }

        // Skip Identifier nodes (built-in types like int, string, etc.)
        // These are tracked internally but not emitted to SCIP as they use
        // a synthetic symbol format that SCIP validators don't recognize.

        return [];
    }

    /**
     * Collect type symbols from a type node.
     * Used internally to gather constituent symbols for composite types.
     *
     * @return list<non-empty-string>
     */
    private function collectTypeSymbols(Node $type): array
    {
        if ($type instanceof Name) {
            $symbol = $this->resolveNameToSymbol($type);
            return $symbol !== null ? [$symbol] : [];
        }

        if ($type instanceof Identifier) {
            $builtinSymbol = $this->namer->nameBuiltin($type->name);
            return $builtinSymbol !== null ? [$builtinSymbol] : [];
        }

        if ($type instanceof NullableType) {
            $innerSymbols = $this->collectTypeSymbols($type->type);
            $nullSymbol = $this->namer->nameBuiltin('null');
            if ($nullSymbol !== null) {
                $innerSymbols[] = $nullSymbol;
            }
            return $innerSymbols;
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $symbols = [];
            foreach ($type->types as $subType) {
                $subSymbols = $this->collectTypeSymbols($subType);
                foreach ($subSymbols as $s) {
                    $symbols[] = $s;
                }
            }
            return $symbols;
        }

        return [];
    }

    /**
     * Resolve a Name node to a SCIP symbol.
     *
     * @return ?non-empty-string
     */
    private function resolveNameToSymbol(Name $name): ?string
    {
        $nameStr = $name->toString();

        // Handle built-in type-like names
        $builtinSymbol = $this->namer->nameBuiltin($nameStr);
        if ($builtinSymbol !== null) {
            return $builtinSymbol;
        }

        // Skip self/static/parent - they need class context
        if (in_array(strtolower($nameStr), ['self', 'static', 'parent'], true)) {
            // Try to resolve via Types
            return $this->types->nameDef($name);
        }

        // Try to resolve via Types
        return $this->types->nameDef($name);
    }

    /**
     * Register a synthetic type symbol for later emission.
     *
     * NOTE: Currently disabled as SCIP validators don't recognize synthetic
     * type symbol formats. The synthetic types are still tracked internally
     * for use in calls.json but not emitted to the SCIP index.
     *
     * @param  non-empty-string       $symbol        The synthetic type symbol
     * @param  list<non-empty-string> $constituents  The constituent type symbols
     * @param  bool                   $isIntersection  True for intersection, false for union
     */
    private function registerSyntheticType(string $symbol, array $constituents, bool $isIntersection): void // phpcs:ignore
    {
        // Currently disabled - SCIP validators don't recognize synthetic symbol formats
        // The synthetic types are tracked internally for calls.json but not emitted to SCIP

        // Skip if already registered
        // if (isset($this->ctx->syntheticTypeSymbols[$symbol])) {
        //     return;
        // }
        //
        // $relationships = [];
        // foreach ($constituents as $constituentSymbol) {
        //     $relationships[] = new Relationship([
        //         'symbol'             => $constituentSymbol,
        //         'is_type_definition' => true,
        //     ]);
        // }
        //
        // $this->ctx->syntheticTypeSymbols[$symbol] = new SymbolInformation([
        //     'symbol'        => $symbol,
        //     'kind'          => Kind::TypeAlias,
        //     'relationships' => $relationships,
        // ]);
    }

    /**
     * @param  non-empty-string  $tagName
     * @param  non-empty-string  $name
     * @param  non-empty-string  $symbol
     */
    private function docDef(
        ?Doc $doc,
        string $tagName,
        PhpDocTagValueNode $node,
        string $name,
        string $symbol,
        int $kind = SyntaxKind::Identifier,
    ): void {
        if ($doc === null) {
            return;
        }

        $startLine = $node->getAttribute(Attribute::START_LINE);
        if (!is_int($startLine)) {
            return;
        }

        $endLine = $node->getAttribute(Attribute::END_LINE);
        if (!is_int($endLine)) {
            return;
        }

        $lines = explode("\n", $doc->getText());
        $relevantLines = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
        $text = implode("\n", $relevantLines);

        $pos = PosResolver::posInDoc($text, $doc->getStartLine() + $startLine - 2, $tagName, $name);

        $this->ctx->occurrences[] = new Occurrence([
            'range'        => $pos,
            'symbol'       => $symbol,
            'symbol_roles' => SymbolRole::Definition,
            'syntax_kind'  => $kind,
        ]);
    }

    /** @param  non-empty-string  $symbol */
    private function ref(
        PosResolver $pos,
        string $symbol,
        Node $posNode,
        int $kind = SyntaxKind::Identifier,
        int $role = SymbolRole::UnspecifiedSymbolRole,
    ): void {
        if (!mb_check_encoding($symbol, 'UTF-8')) {
            return;
        }
        if (!str_starts_with($symbol, 'local ')) {
            $ident = $this->namer->extractIdent($symbol);
            if ($this->composer->isDependency($ident)) {
                $this->ctx->extSymbols[$symbol] = new SymbolInformation([
                    'symbol'        => $symbol,
                    'documentation' => [], // TODO(drj): build hover content
                ]);
            }
        }

        $this->ctx->occurrences[] = new Occurrence([
            'range'        => $pos->pos($posNode),
            'symbol'       => $symbol,
            'symbol_roles' => $role,
            'syntax_kind'  => $kind,
        ]);
    }

    /**
     * Register a parameter's type in localVars for type resolution.
     *
     * This enables property/method access on parameters to resolve correctly.
     * For example, with `function foo(Message $msg)`, we can resolve `$msg->contact`.
     */
    private function registerParameterType(Param $n): void
    {
        if (!($n->var instanceof Variable) || !is_string($n->var->name)) {
            return;
        }

        if ($n->type === null) {
            return;
        }

        $scope = $this->typeResolver->findEnclosingScope($n);
        if ($scope === null) {
            return;
        }

        $this->types->setCurrentScope($scope);
        $paramType = $this->types->parseType($n->type);
        if ($paramType !== null) {
            $this->types->registerLocalVarWithType($n->var->name, $paramType);
        }
    }

    /**
     * Create a ValueRecord for a parameter at its declaration site.
     *
     * Per the "One Value Per Declaration Rule", each parameter should have
     * exactly ONE value entry at its declaration site (function signature).
     * All usages of the parameter should reference this single value.
     *
     * @param PosResolver $pos Position resolver for source locations
     * @param Param $n The parameter node
     * @param ?string $promotedPropertySymbol SCIP symbol of the promoted Property (for constructor promotion)
     */
    private function createParameterValueRecord(PosResolver $pos, Param $n, ?string $promotedPropertySymbol = null): void
    {
        if (!($n->var instanceof Variable) || !is_string($n->var->name)) {
            return;
        }

        $scope = $this->typeResolver->findEnclosingScope($n);
        if ($scope === null) {
            return;
        }

        // Get the parameter symbol
        $paramSymbol = $this->namer->name($n);
        if ($paramSymbol === null) {
            return;
        }

        // Check if we already created a value for this parameter
        if (isset($this->ctx->parameterValueIds[$paramSymbol])) {
            return;
        }

        // Compute value ID from parameter position in function signature
        $posData = $pos->pos($n->var);
        $line = $posData[0] + 1; // Convert 0-based to 1-based
        $col = $posData[1];      // Keep 0-based per spec

        $id = $this->ctx->relativePath . ':' . $line . ':' . $col;

        $location = [
            'file' => $this->ctx->relativePath,
            'line' => $line,
            'col'  => $col,
        ];

        // Resolve parameter type
        $type = null;
        if ($n->type !== null) {
            $this->types->setCurrentScope($scope);
            $paramType = $this->types->parseType($n->type);
            $type = $this->typeResolver->formatTypeSymbol($paramType);
        }

        // Create ValueRecord for this parameter at declaration site
        $valueRecord = new ValueRecord(
            id: $id,
            kind: 'parameter',
            symbol: $paramSymbol,
            type: $type,
            location: $location,
            sourceCallId: null, // Parameters don't have a source call
            sourceValueId: null,
            promotedPropertySymbol: $promotedPropertySymbol,
        );

        $this->ctx->values[] = $valueRecord;

        // Store the value ID in parameterValueIds map for later lookup
        $this->ctx->parameterValueIds[$paramSymbol] = $id;

        // Also track in expressionIds for the variable node
        $this->ctx->expressionIds[spl_object_id($n->var)] = $id;
    }

    /**
     * Handle local variable assignment.
     *
     * For SCIP index: Uses 'local N' format (required by SCIP validator)
     * For calls.json: Uses descriptive format '{scope}local${name}@{line}'
     */
    private function handleLocalVariable(PosResolver $pos, Assign $n): void
    {
        if (!($n->var instanceof Variable) || !is_string($n->var->name)) {
            return;
        }

        $varName = $n->var->name;
        $scope = $this->typeResolver->findEnclosingScope($n);

        if ($scope === null) {
            return;
        }

        // Set the scope in Types for proper type tracking
        $this->types->setCurrentScope($scope);

        // Register the variable type
        $type = $this->types->registerLocalVar($varName, $n->expr);

        // Get the assignment line (1-based)
        $posData = $pos->pos($n->var);
        $line = $posData[0] + 1;

        // Generate lookup key for tracking current symbol per variable
        $key = $scope . '::$' . $varName;

        // Check if this is the first assignment (definition) or a reassignment
        $isFirstAssignment = !isset($this->ctx->localSymbols[$key]);

        // SCIP symbol: Use 'local N' format (required by SCIP validator)
        $scipSymbol = 'local ' . $this->ctx->localCounter++;

        // Calls.json symbol: Use descriptive format '{scope}local${name}@{line}'
        $callsSymbol = $this->namer->nameLocalVar($scope, $varName, $line);

        // Update the current symbols for this variable
        $this->ctx->localSymbols[$key] = $scipSymbol;
        $this->ctx->localCallsSymbols[$key] = $callsSymbol;
        $this->ctx->localAssignmentLines[$key] = $line;

        // Build documentation for the local variable
        $typeStr = $this->typeResolver->formatTypeForDoc($type);
        $doc = ['```php', '$' . $varName . ': ' . $typeStr, '```'];

        // Emit SCIP symbol definition
        $this->ctx->symbols[$scipSymbol] = new SymbolInformation([
            'symbol'        => $scipSymbol,
            'documentation' => $doc,
        ]);

        // Emit SCIP occurrence (definition for first, write access for reassignment)
        $this->ctx->occurrences[] = new Occurrence([
            'range'        => $pos->pos($n->var),
            'symbol'       => $scipSymbol,
            'symbol_roles' => $isFirstAssignment ? SymbolRole::Definition : SymbolRole::WriteAccess,
            'syntax_kind'  => SyntaxKind::IdentifierLocal,
        ]);

        // Track the RHS expression and determine source_call_id or source_value_id
        $sourceCallId = null;
        $sourceValueId = null;

        // First, track the RHS expression to get its ID
        $rhsId = $this->trackExpression($pos, $n->expr);

        // Determine if RHS is a call or a value
        if ($rhsId !== null) {
            if ($this->isCallExpression($n->expr)) {
                $sourceCallId = $rhsId;
            } else {
                $sourceValueId = $rhsId;
            }
        }

        // Create ValueRecord for this local variable assignment (uses descriptive symbol for calls.json)
        $col = $posData[1];
        $id = $this->ctx->relativePath . ':' . $line . ':' . $col;

        $location = [
            'file' => $this->ctx->relativePath,
            'line' => $line,
            'col'  => $col,
        ];

        $valueRecord = new ValueRecord(
            id: $id,
            kind: 'local',
            symbol: $callsSymbol,  // Use descriptive symbol for calls.json
            type: $this->typeResolver->resolveExpressionReturnType($n->var),
            location: $location,
            sourceCallId: $sourceCallId,
            sourceValueId: $sourceValueId,
        );

        $this->ctx->values[] = $valueRecord;

        // Track the value ID for this local symbol (for source_value_id linking)
        // Use both SCIP symbol and calls symbol for lookups
        $this->ctx->localValueIds[$scipSymbol] = $id;
        $this->ctx->localValueIds[$callsSymbol] = $id;
        $this->ctx->expressionIds[spl_object_id($n->var)] = $id;
    }

    /**
     * Check if an expression is a call expression (produces a call record).
     */
    private function isCallExpression(Node\Expr $expr): bool
    {
        return $expr instanceof MethodCall
            || $expr instanceof NullsafeMethodCall
            || $expr instanceof StaticCall
            || $expr instanceof FuncCall
            || $expr instanceof New_
            || $expr instanceof PropertyFetch
            || $expr instanceof NullsafePropertyFetch
            || $expr instanceof StaticPropertyFetch
            || $expr instanceof ArrayDimFetch
            || $expr instanceof Coalesce
            || $expr instanceof Ternary
            || $expr instanceof Match_;
    }

    /**
     * Handle foreach loop variable.
     *
     * For SCIP index: Uses 'local N' format (required by SCIP validator)
     * For calls.json: Uses descriptive format '{scope}local${name}@{line}'
     */
    private function handleForeachVariable(PosResolver $pos, Foreach_ $n): void
    {
        if (!($n->valueVar instanceof Variable) || !is_string($n->valueVar->name)) {
            return;
        }

        $varName = $n->valueVar->name;
        $scope = $this->typeResolver->findEnclosingScope($n);

        if ($scope === null) {
            return;
        }

        // Set the scope in Types for proper type tracking
        $this->types->setCurrentScope($scope);

        // Get the element type from the iterable
        $elementType = $this->types->getForeachElementType($n->expr);

        // Register the variable type in Types
        $this->types->registerLocalVarWithType($varName, $elementType);

        // Get the foreach line (1-based)
        $posData = $pos->pos($n->valueVar);
        $line = $posData[0] + 1;

        // Generate lookup key for tracking current symbol per variable
        $key = $scope . '::$' . $varName;

        // Check if this is the first assignment (definition) or a reassignment
        $isFirstAssignment = !isset($this->ctx->localSymbols[$key]);

        // SCIP symbol: Use 'local N' format (required by SCIP validator)
        $scipSymbol = 'local ' . $this->ctx->localCounter++;

        // Calls.json symbol: Use descriptive format '{scope}local${name}@{line}'
        $callsSymbol = $this->namer->nameLocalVar($scope, $varName, $line);

        // Update the current symbols for this variable
        $this->ctx->localSymbols[$key] = $scipSymbol;
        $this->ctx->localCallsSymbols[$key] = $callsSymbol;
        $this->ctx->localAssignmentLines[$key] = $line;

        // Build documentation for the foreach variable
        $typeStr = $this->typeResolver->formatTypeForDoc($elementType);
        $doc = ['```php', '$' . $varName . ': ' . $typeStr, '```'];

        // Emit SCIP symbol definition
        $this->ctx->symbols[$scipSymbol] = new SymbolInformation([
            'symbol'        => $scipSymbol,
            'documentation' => $doc,
        ]);

        $this->ctx->occurrences[] = new Occurrence([
            'range'        => $pos->pos($n->valueVar),
            'symbol'       => $scipSymbol,
            'symbol_roles' => $isFirstAssignment ? SymbolRole::Definition : SymbolRole::WriteAccess,
            'syntax_kind'  => SyntaxKind::IdentifierLocal,
        ]);

        // Create ValueRecord for this foreach variable (uses descriptive symbol for calls.json)
        $col = $posData[1];
        $id = $this->ctx->relativePath . ':' . $line . ':' . $col;

        $location = [
            'file' => $this->ctx->relativePath,
            'line' => $line,
            'col'  => $col,
        ];

        // For foreach, the source is the iterable expression
        $sourceCallId = null;
        $sourceValueId = null;
        $iterableId = $this->trackExpression($pos, $n->expr);
        if ($iterableId !== null) {
            if ($this->isCallExpression($n->expr)) {
                $sourceCallId = $iterableId;
            } else {
                $sourceValueId = $iterableId;
            }
        }

        $valueRecord = new ValueRecord(
            id: $id,
            kind: 'local',
            symbol: $callsSymbol,  // Use descriptive symbol for calls.json
            type: $this->typeResolver->formatTypeSymbol($elementType),
            location: $location,
            sourceCallId: $sourceCallId,
            sourceValueId: $sourceValueId,
        );

        $this->ctx->values[] = $valueRecord;
        $this->ctx->localValueIds[$scipSymbol] = $id;
        $this->ctx->localValueIds[$callsSymbol] = $id;
        $this->ctx->expressionIds[spl_object_id($n->valueVar)] = $id;

        // Also handle key variable if present
        if ($n->keyVar instanceof Variable && is_string($n->keyVar->name)) {
            $keyVarName = $n->keyVar->name;
            $keyKey = $scope . '::$' . $keyVarName;

            $keyPosData = $pos->pos($n->keyVar);
            $keyLine = $keyPosData[0] + 1;

            $keyIsFirstAssignment = !isset($this->ctx->localSymbols[$keyKey]);

            // SCIP symbol for key
            $keyScipSymbol = 'local ' . $this->ctx->localCounter++;

            // Calls.json symbol for key
            $keyCallsSymbol = $this->namer->nameLocalVar($scope, $keyVarName, $keyLine);

            $this->ctx->localSymbols[$keyKey] = $keyScipSymbol;
            $this->ctx->localCallsSymbols[$keyKey] = $keyCallsSymbol;
            $this->ctx->localAssignmentLines[$keyKey] = $keyLine;

            // Key type is typically int|string
            $doc = ['```php', '$' . $keyVarName . ': int|string', '```'];

            $this->ctx->symbols[$keyScipSymbol] = new SymbolInformation([
                'symbol'        => $keyScipSymbol,
                'documentation' => $doc,
            ]);

            $this->ctx->occurrences[] = new Occurrence([
                'range'        => $pos->pos($n->keyVar),
                'symbol'       => $keyScipSymbol,
                'symbol_roles' => $keyIsFirstAssignment ? SymbolRole::Definition : SymbolRole::WriteAccess,
                'syntax_kind'  => SyntaxKind::IdentifierLocal,
            ]);

            // Create ValueRecord for the key variable
            $keyCol = $keyPosData[1];
            $keyId = $this->ctx->relativePath . ':' . $keyLine . ':' . $keyCol;

            $keyLocation = [
                'file' => $this->ctx->relativePath,
                'line' => $keyLine,
                'col'  => $keyCol,
            ];

            $keyValueRecord = new ValueRecord(
                id: $keyId,
                kind: 'local',
                symbol: $keyCallsSymbol,  // Use descriptive symbol for calls.json
                type: $this->namer->nameUnion([
                    $this->namer->nameBuiltin('int') ?? 'int',
                    $this->namer->nameBuiltin('string') ?? 'string',
                ]),
                location: $keyLocation,
                sourceCallId: $sourceCallId,
                sourceValueId: $sourceValueId,
            );

            $this->ctx->values[] = $keyValueRecord;
            $this->ctx->localValueIds[$keyScipSymbol] = $keyId;
            $this->ctx->localValueIds[$keyCallsSymbol] = $keyId;
            $this->ctx->expressionIds[spl_object_id($n->keyVar)] = $keyId;
        }
    }

    /**
     * Handle local variable reference (read).
     */
    private function handleLocalVariableRef(PosResolver $pos, Variable $n): void
    {
        if (!is_string($n->name)) {
            return;
        }

        $varName = $n->name;
        $scope = $this->typeResolver->findEnclosingScope($n);

        if ($scope === null) {
            return;
        }

        // Set the scope in Types for type lookup
        $this->types->setCurrentScope($scope);

        // Check if this variable is used inside a foreach loop before it's registered.
        // Due to post-order traversal, loop body is visited before the Foreach_ node.
        $this->ensureForeachVarRegistered($pos, $n, $varName, $scope);

        // Check if we have a symbol for this variable (local variables shadow parameters)
        $key = $scope . '::$' . $varName;

        if (isset($this->ctx->localSymbols[$key])) {
            $localSymbol = $this->ctx->localSymbols[$key];
            $this->ctx->occurrences[] = new Occurrence([
                'range'        => $pos->pos($n),
                'symbol'       => $localSymbol,
                'symbol_roles' => SymbolRole::UnspecifiedSymbolRole,
                'syntax_kind'  => SyntaxKind::IdentifierLocal,
            ]);
            return;
        }

        // Check if this is a parameter reference
        $paramSymbol = $scope . '($' . $varName . ')';
        if (isset($this->ctx->symbols[$paramSymbol])) {
            $this->ctx->occurrences[] = new Occurrence([
                'range'        => $pos->pos($n),
                'symbol'       => $paramSymbol,
                'symbol_roles' => SymbolRole::UnspecifiedSymbolRole,
                'syntax_kind'  => SyntaxKind::IdentifierParameter,
            ]);
        }
    }

    /**
     * Eagerly register foreach loop variables when referenced in loop body.
     *
     * Due to post-order traversal (leaveNode), the loop body is visited BEFORE
     * the Foreach_ node itself. This means variables used inside the loop body
     * are encountered before handleForeachVariable() can register them.
     *
     * This method checks if the variable is defined by an ancestor Foreach_ node
     * and registers it early if needed.
     */
    private function ensureForeachVarRegistered(PosResolver $pos, Variable $n, string $varName, string $scope): void
    {
        // Walk up the AST to find an enclosing Foreach_ that defines this variable
        $parent = $n->getAttribute('parent');
        while ($parent !== null) {
            if ($parent instanceof Foreach_) {
                // Check if this foreach defines the variable we're looking for
                if (
                    $parent->valueVar instanceof Variable
                    && is_string($parent->valueVar->name)
                    && $parent->valueVar->name === $varName
                ) {
                    $this->registerForeachVar($pos, $parent, $varName, $scope, $parent->valueVar, false);
                    return;
                }
                // Check key variable
                if (
                    $parent->keyVar instanceof Variable
                    && is_string($parent->keyVar->name)
                    && $parent->keyVar->name === $varName
                ) {
                    $this->registerForeachVar($pos, $parent, $varName, $scope, $parent->keyVar, true);
                    return;
                }
            }
            // Stop at function/method boundary
            if ($parent instanceof ClassMethod || $parent instanceof Function_) {
                break;
            }
            $parent = $parent->getAttribute('parent');
        }
    }

    /**
     * Register a foreach loop variable as a local symbol.
     * Called by ensureForeachVarRegistered() for early registration.
     *
     * For SCIP index: Uses 'local N' format (required by SCIP validator)
     * For calls.json: Uses descriptive format '{scope}local${name}@{line}'
     */
    private function registerForeachVar(
        PosResolver $pos,
        Foreach_ $foreach,
        string $varName,
        string $scope,
        Variable $varNode,
        bool $isKey,
    ): void {
        $key = $scope . '::$' . $varName;

        // Already registered - nothing to do
        if (isset($this->ctx->localSymbols[$key])) {
            return;
        }

        // Get the line number for the foreach variable
        $posData = $pos->pos($varNode);
        $line = $posData[0] + 1;

        // SCIP symbol: Use 'local N' format (required by SCIP validator)
        $scipSymbol = 'local ' . $this->ctx->localCounter++;

        // Calls.json symbol: Use descriptive format '{scope}local${name}@{line}'
        $callsSymbol = $this->namer->nameLocalVar($scope, $varName, $line);

        $this->ctx->localSymbols[$key] = $scipSymbol;
        $this->ctx->localCallsSymbols[$key] = $callsSymbol;
        $this->ctx->localAssignmentLines[$key] = $line;

        // Get type for documentation
        if ($isKey) {
            $typeStr = 'int|string';
            $typeSymbol = $this->namer->nameUnion([
                $this->namer->nameBuiltin('int') ?? 'int',
                $this->namer->nameBuiltin('string') ?? 'string',
            ]);
        } else {
            $elementType = $this->types->getForeachElementType($foreach->expr);
            $typeStr = $this->typeResolver->formatTypeForDoc($elementType);
            $typeSymbol = $this->typeResolver->formatTypeSymbol($elementType);
        }

        $doc = ['```php', '$' . $varName . ': ' . $typeStr, '```'];

        // Register SCIP symbol info
        $this->ctx->symbols[$scipSymbol] = new SymbolInformation([
            'symbol'        => $scipSymbol,
            'documentation' => $doc,
        ]);

        // Emit SCIP definition occurrence at the foreach variable position
        $this->ctx->occurrences[] = new Occurrence([
            'range'        => $pos->pos($varNode),
            'symbol'       => $scipSymbol,
            'symbol_roles' => SymbolRole::Definition,
            'syntax_kind'  => SyntaxKind::IdentifierLocal,
        ]);

        // Create ValueRecord for this foreach variable (uses descriptive symbol for calls.json)
        $col = $posData[1];
        $id = $this->ctx->relativePath . ':' . $line . ':' . $col;

        $location = [
            'file' => $this->ctx->relativePath,
            'line' => $line,
            'col'  => $col,
        ];

        // Track the source (iterable expression)
        $sourceCallId = null;
        $sourceValueId = null;
        $iterableId = $this->trackExpression($pos, $foreach->expr);
        if ($iterableId !== null) {
            if ($this->isCallExpression($foreach->expr)) {
                $sourceCallId = $iterableId;
            } else {
                $sourceValueId = $iterableId;
            }
        }

        $valueRecord = new ValueRecord(
            id: $id,
            kind: 'local',
            symbol: $callsSymbol,  // Use descriptive symbol for calls.json
            type: $typeSymbol,
            location: $location,
            sourceCallId: $sourceCallId,
            sourceValueId: $sourceValueId,
        );

        $this->ctx->values[] = $valueRecord;
        $this->ctx->localValueIds[$scipSymbol] = $id;
        $this->ctx->localValueIds[$callsSymbol] = $id;
        $this->ctx->expressionIds[spl_object_id($varNode)] = $id;
    }

    /**
     * Build a value record for value-producing expressions (variables, parameters, literals, constants).
     *
     * In v3, these create ValueRecords instead of CallRecords.
     *
     * @param  PosResolver  $pos            Position resolver for source locations
     * @param  Node\Expr    $exprNode       The expression node
     * @param  string       $kind           Value kind: local, parameter, literal, constant
     * @param  ?string      $symbol         SCIP symbol (null for literals)
     * @param  ?string      $sourceCallId   ID of call this value was assigned from
     * @param  ?string      $sourceValueId  ID of value this was assigned from
     * @return ?ValueRecord The value record, or null if cannot be created
     */
    private function buildValueRecord(
        PosResolver $pos,
        Node\Expr $exprNode,
        string $kind,
        ?string $symbol,
        ?string $sourceCallId = null,
        ?string $sourceValueId = null,
    ): ?ValueRecord {
        $posData = $pos->pos($exprNode);
        $line = $posData[0] + 1; // Convert 0-based to 1-based
        $col = $posData[1];      // Keep 0-based per spec

        // Compute unique ID: file:line:col
        $id = $this->ctx->relativePath . ':' . $line . ':' . $col;

        $location = [
            'file' => $this->ctx->relativePath,
            'line' => $line,
            'col'  => $col,
        ];

        // Resolve type for the expression
        $type = $this->typeResolver->resolveExpressionReturnType($exprNode);

        $record = new ValueRecord(
            id: $id,
            kind: $kind,
            symbol: $symbol,
            type: $type,
            location: $location,
            sourceCallId: $sourceCallId,
            sourceValueId: $sourceValueId,
        );

        // Track the value ID for value_id linking
        $this->ctx->expressionIds[spl_object_id($exprNode)] = $id;

        return $record;
    }

    /**
     * Get the ID for an expression, if it has been tracked.
     *
     * @param  Node\Expr  $expr  The expression node
     * @return ?string    The ID (value or call), or null if not tracked
     */
    private function getExpressionId(Node\Expr $expr): ?string
    {
        return $this->ctx->expressionIds[spl_object_id($expr)] ?? null;
    }

    /**
     * Track an expression tree and return the ID for the root expression.
     *
     * This recursively processes an expression, creating records:
     * - ValueRecords for: variables, parameters, literals, constants
     * - CallRecords for: property access, array access, operators, method/function calls
     *
     * @param  PosResolver  $pos   Position resolver
     * @param  Node\Expr    $expr  The expression to track
     * @return ?string      The ID (value or call) for this expression, or null if not trackable
     */
    private function trackExpression(PosResolver $pos, Node\Expr $expr): ?string
    {
        // Check if already tracked
        $existingId = $this->getExpressionId($expr);
        if ($existingId !== null) {
            return $existingId;
        }

        // Handle different expression types
        return match (true) {
            // Values (create ValueRecords)
            $expr instanceof Variable => $this->trackVariableExpression($pos, $expr),
            $expr instanceof Scalar => $this->trackLiteralExpression($pos, $expr),
            $expr instanceof Node\Expr\Array_ => $this->trackLiteralExpression($pos, $expr),
            $expr instanceof ConstFetch => $this->trackConstFetchExpression($pos, $expr),
            $expr instanceof ClassConstFetch => $this->trackClassConstFetchExpression($pos, $expr),
            // Calls (create CallRecords)
            $expr instanceof PropertyFetch => $this->trackPropertyFetchExpression($pos, $expr),
            $expr instanceof NullsafePropertyFetch => $this->trackNullsafePropertyFetchExpression($pos, $expr),
            $expr instanceof StaticPropertyFetch => $this->trackStaticPropertyFetchExpression($pos, $expr),
            $expr instanceof ArrayDimFetch => $this->trackArrayDimFetchExpression($pos, $expr),
            // Coalesce extends BinaryOp, so check it BEFORE generic BinaryOp
            $expr instanceof Coalesce => $this->trackCoalesceExpression($pos, $expr),
            $expr instanceof Ternary => $this->trackTernaryExpression($pos, $expr),
            $expr instanceof Match_ => $this->trackMatchExpression($pos, $expr),
            // BinaryOp (comparisons, arithmetic) - treat as literal result
            $expr instanceof BinaryOp => $this->trackBinaryOpExpression($pos, $expr),
            $expr instanceof MethodCall,
            $expr instanceof NullsafeMethodCall,
            $expr instanceof StaticCall,
            $expr instanceof FuncCall,
            $expr instanceof New_ => $this->getExpressionId($expr), // Already tracked by index()
            default => null, // Untracked expression types
        };
    }

    /**
     * Track a variable access expression.
     *
     * Per the "One Value Per Declaration Rule", variables should NOT create
     * new ValueRecords for each usage. Instead, this method looks up the
     * existing declaration value ID and returns it.
     *
     * - Parameters: Look up in parameterValueIds (created at declaration in Param handling)
     * - Locals: Look up in localValueIds (created at assignment in handleLocalVariable)
     *
     * @param PosResolver $pos Position resolver
     * @param Variable $expr The variable expression
     * @return ?string The existing value ID at declaration/assignment, or null if not found
     */
    private function trackVariableExpression(PosResolver $pos, Variable $expr): ?string
    {
        if (!is_string($expr->name)) {
            return null;
        }

        // Skip $this - it's not really a value-producing expression
        if ($expr->name === 'this') {
            return null;
        }

        // Determine the scope for this variable
        $scope = $this->typeResolver->findEnclosingScope($expr);
        if ($scope === null) {
            return null;
        }

        // Check for local variable first (locals shadow parameters)
        $key = $scope . '::$' . $expr->name;

        // 1. Check for local variable - look up existing value ID from localValueIds
        if (isset($this->ctx->localCallsSymbols[$key])) {
            $callsSymbol = $this->ctx->localCallsSymbols[$key];
            // Return the existing value ID from declaration/assignment
            if (isset($this->ctx->localValueIds[$callsSymbol])) {
                return $this->ctx->localValueIds[$callsSymbol];
            }
        }

        // 2. Check for parameter - look up existing value ID from parameterValueIds
        $paramSymbol = $scope . '($' . $expr->name . ')';
        if (isset($this->ctx->parameterValueIds[$paramSymbol])) {
            return $this->ctx->parameterValueIds[$paramSymbol];
        }

        // Fallback: symbol is registered but no value ID found (shouldn't happen in normal usage)
        // This can occur for variables that weren't properly tracked at declaration.
        // Return null instead of creating a duplicate value entry.
        return null;
    }

    /**
     * Track a property fetch expression.
     * In v3, property access creates a CallRecord with kind: access.
     */
    private function trackPropertyFetchExpression(PosResolver $pos, PropertyFetch $expr): ?string
    {
        if (!($expr->name instanceof Identifier)) {
            return null;
        }

        // First track the receiver
        $receiverValueId = $this->trackExpression($pos, $expr->var);

        // Resolve the property symbol
        $propName = $expr->name->toString();
        $symbol = $this->types->propDef($expr->var, $propName);

        $record = $this->callBuilder->buildAccessOrOperatorCallRecord(
            pos: $pos,
            exprNode: $expr,
            symbol: $symbol,
            receiverValueId: $receiverValueId,
            positionNode: $expr->name,
        );

        if ($record !== null) {
            $this->callBuilder->addCallWithResultValue($record);
            return $record->id;
        }

        return null;
    }

    /**
     * Track a nullsafe property fetch expression.
     * In v3, nullsafe property access creates a CallRecord with kind: access_nullsafe.
     */
    private function trackNullsafePropertyFetchExpression(PosResolver $pos, NullsafePropertyFetch $expr): ?string
    {
        if (!($expr->name instanceof Identifier)) {
            return null;
        }

        // First track the receiver
        $receiverValueId = $this->trackExpression($pos, $expr->var);

        // Resolve the property symbol
        $propName = $expr->name->toString();
        $symbol = $this->types->propDef($expr->var, $propName);

        $record = $this->callBuilder->buildAccessOrOperatorCallRecord(
            pos: $pos,
            exprNode: $expr,
            symbol: $symbol,
            receiverValueId: $receiverValueId,
            positionNode: $expr->name,
        );

        if ($record !== null) {
            $this->callBuilder->addCallWithResultValue($record);
            return $record->id;
        }

        return null;
    }

    /**
     * Track a static property fetch expression.
     * In v3, static property access creates a CallRecord with kind: access_static.
     */
    private function trackStaticPropertyFetchExpression(PosResolver $pos, StaticPropertyFetch $expr): ?string
    {
        if (!($expr->name instanceof Identifier)) {
            return null;
        }

        // Resolve the property symbol
        $propName = $expr->name->toString();
        $symbol = $this->types->propDef($expr->class, $propName);

        $record = $this->callBuilder->buildAccessOrOperatorCallRecord(
            pos: $pos,
            exprNode: $expr,
            symbol: $symbol,
            positionNode: $expr->name,
        );

        if ($record !== null) {
            $this->callBuilder->addCallWithResultValue($record);
            return $record->id;
        }

        return null;
    }

    /**
     * Track a class constant fetch expression.
     * In v3, class constants create ValueRecords with kind: constant.
     */
    private function trackClassConstFetchExpression(PosResolver $pos, ClassConstFetch $expr): ?string
    {
        if (!($expr->name instanceof Identifier)) {
            return null;
        }

        $constName = $expr->name->toString();
        if ($constName === 'class') {
            // ::class is a special case, not a real constant access
            return null;
        }

        // Resolve the constant symbol
        $symbol = $this->types->constDef($expr->class, $constName);

        $record = $this->buildValueRecord(
            pos: $pos,
            exprNode: $expr,
            kind: 'constant',
            symbol: $symbol,
        );

        if ($record !== null) {
            $this->ctx->values[] = $record;
            return $record->id;
        }

        return null;
    }

    /**
     * Track a global constant fetch expression.
     * In v3, global constants create ValueRecords with kind: constant or literal.
     */
    private function trackConstFetchExpression(PosResolver $pos, ConstFetch $expr): ?string
    {
        // Global constants like true, false, null
        $constName = $expr->name->toString();

        // Skip true/false/null - they're literals, not constants
        if (in_array(strtolower($constName), ['true', 'false', 'null'], true)) {
            return $this->trackLiteralExpression($pos, $expr);
        }

        // Resolve the constant symbol
        $symbol = $this->types->nameDef($expr->name);

        $record = $this->buildValueRecord(
            pos: $pos,
            exprNode: $expr,
            kind: 'constant',
            symbol: $symbol,
        );

        if ($record !== null) {
            $this->ctx->values[] = $record;
            return $record->id;
        }

        return null;
    }

    /**
     * Track an array dimension fetch expression.
     * In v3, array access creates a CallRecord with kind: access_array.
     *
     * Note: access_array is experimental and only tracked with --experimental flag.
     */
    private function trackArrayDimFetchExpression(PosResolver $pos, ArrayDimFetch $expr): ?string
    {
        // Skip experimental kind unless --experimental flag is set
        if (!$this->ctx->experimental) {
            return null;
        }

        // Check if already tracked
        $existingId = $this->getExpressionId($expr);
        if ($existingId !== null) {
            return $existingId;
        }

        // Track the array variable
        $receiverValueId = $this->trackExpression($pos, $expr->var);

        // Track the key expression if it exists
        $keyValueId = null;
        if ($expr->dim !== null) {
            $keyValueId = $this->trackExpression($pos, $expr->dim);
        }

        $record = $this->callBuilder->buildAccessOrOperatorCallRecord(
            pos: $pos,
            exprNode: $expr,
            symbol: null, // Array access has no symbol
            receiverValueId: $receiverValueId,
            keyValueId: $keyValueId,
        );

        if ($record !== null) {
            $this->callBuilder->addCallWithResultValue($record);
            return $record->id;
        }

        return null;
    }

    /**
     * Track a null coalesce expression.
     * In v3, coalesce creates a CallRecord with kind: coalesce.
     *
     * Note: coalesce is experimental and only tracked with --experimental flag.
     */
    private function trackCoalesceExpression(PosResolver $pos, Coalesce $expr): ?string
    {
        // Skip experimental kind unless --experimental flag is set
        if (!$this->ctx->experimental) {
            return null;
        }

        // Check if already tracked
        $existingId = $this->getExpressionId($expr);
        if ($existingId !== null) {
            return $existingId;
        }

        // Track both operands first - this may create call records that we need
        // to avoid duplicating
        $leftValueId = $this->trackExpression($pos, $expr->left);
        $rightValueId = $this->trackExpression($pos, $expr->right);

        // Check if left operand is a call at the same position as this coalesce
        // If so, we should skip creating the coalesce call to avoid duplicates
        $posData = $pos->pos($expr);
        $id = $this->ctx->relativePath . ':' . ($posData[0] + 1) . ':' . $posData[1];

        // If a call already exists at this position, don't create a duplicate
        foreach ($this->ctx->calls as $call) {
            if ($call->id === $id) {
                // Store the ID for this expression to avoid re-tracking
                $this->ctx->expressionIds[spl_object_id($expr)] = $id;
                return $id;
            }
        }

        $record = $this->callBuilder->buildAccessOrOperatorCallRecord(
            pos: $pos,
            exprNode: $expr,
            symbol: $this->namer->nameOperator('coalesce'),
            leftValueId: $leftValueId,
            rightValueId: $rightValueId,
        );

        if ($record !== null) {
            $this->callBuilder->addCallWithResultValue($record);
            return $record->id;
        }

        return null;
    }

    /**
     * Track a binary operation expression (comparison, arithmetic, etc).
     *
     * For binary operations, we track the left operand's value ID rather than
     * creating a separate value for the result. This is because:
     * 1. Binary ops often have the same position as their parent (e.g., ternary condition)
     * 2. The left operand represents the "main" data being operated on
     * 3. This avoids creating duplicate values at the same position
     *
     * Coalesce (which extends BinaryOp) is handled separately in trackCoalesceExpression.
     */
    private function trackBinaryOpExpression(PosResolver $pos, BinaryOp $expr): ?string
    {
        // Return the left operand's value ID
        // This represents the primary value being evaluated in the comparison
        return $this->trackExpression($pos, $expr->left);
    }

    /**
     * Track a ternary expression (elvis or full ternary).
     * In v3, ternary creates a CallRecord with kind: ternary or ternary_full.
     *
     * Note: ternary and ternary_full are experimental and only tracked with --experimental flag.
     */
    private function trackTernaryExpression(PosResolver $pos, Ternary $expr): ?string
    {
        // Skip experimental kind unless --experimental flag is set
        if (!$this->ctx->experimental) {
            return null;
        }

        // Check if already tracked
        $existingId = $this->getExpressionId($expr);
        if ($existingId !== null) {
            return $existingId;
        }

        if ($expr->if === null) {
            // Elvis operator: $a ?: $b
            // For short ternary, condition is also the true value
            $conditionValueId = $this->trackExpression($pos, $expr->cond);
            $falseValueId = $this->trackExpression($pos, $expr->else);

            $record = $this->callBuilder->buildAccessOrOperatorCallRecord(
                pos: $pos,
                exprNode: $expr,
                symbol: $this->namer->nameOperator('elvis'),
                conditionValueId: $conditionValueId,
                falseValueId: $falseValueId,
            );
        } else {
            // Full ternary: $a ? $b : $c
            $conditionValueId = $this->trackExpression($pos, $expr->cond);
            $trueValueId = $this->trackExpression($pos, $expr->if);
            $falseValueId = $this->trackExpression($pos, $expr->else);

            $record = $this->callBuilder->buildAccessOrOperatorCallRecord(
                pos: $pos,
                exprNode: $expr,
                symbol: $this->namer->nameOperator('ternary'),
                conditionValueId: $conditionValueId,
                trueValueId: $trueValueId,
                falseValueId: $falseValueId,
            );
        }

        if ($record !== null) {
            $this->callBuilder->addCallWithResultValue($record);
            return $record->id;
        }

        return null;
    }

    /**
     * Track a match expression.
     * In v3, match creates a CallRecord with kind: match.
     *
     * Note: match is experimental and only tracked with --experimental flag.
     */
    private function trackMatchExpression(PosResolver $pos, Match_ $expr): ?string
    {
        // Skip experimental kind unless --experimental flag is set
        if (!$this->ctx->experimental) {
            return null;
        }

        // Check if already tracked
        $existingId = $this->getExpressionId($expr);
        if ($existingId !== null) {
            return $existingId;
        }

        // Track the subject expression
        $subjectValueId = $this->trackExpression($pos, $expr->cond);

        // Track each arm's result expression
        $armIds = [];
        foreach ($expr->arms as $arm) {
            $armId = $this->trackExpression($pos, $arm->body);
            if ($armId !== null) {
                $armIds[] = $armId;
            }
        }

        $record = $this->callBuilder->buildAccessOrOperatorCallRecord(
            pos: $pos,
            exprNode: $expr,
            symbol: $this->namer->nameOperator('match'),
            subjectValueId: $subjectValueId,
            armIds: $armIds !== [] ? $armIds : null,
        );

        if ($record !== null) {
            $this->callBuilder->addCallWithResultValue($record);
            return $record->id;
        }

        return null;
    }

    /**
     * Track a literal expression.
     * In v3, literals create ValueRecords with kind: literal.
     */
    private function trackLiteralExpression(PosResolver $pos, Node\Expr $expr): ?string
    {
        $record = $this->buildValueRecord(
            pos: $pos,
            exprNode: $expr,
            kind: 'literal',
            symbol: null, // Literals have no symbol
        );

        if ($record !== null) {
            $this->ctx->values[] = $record;
            return $record->id;
        }

        return null;
    }

    /**
     * Reset local variable tracking, values, and calls for a new file.
     */
    public function resetLocals(): void
    {
        $this->ctx->resetLocals();
    }
}
