<?php

declare(strict_types=1);

namespace ScipPhp;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\ClassConstFetch;
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
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use Scip\SyntaxKind;
use ScipPhp\Composer\Composer;
use ScipPhp\Indexing\CallRecordBuilder;
use ScipPhp\Indexing\ExpressionTracker;
use ScipPhp\Indexing\IndexingContext;
use ScipPhp\Indexing\LocalVariableTracker;
use ScipPhp\Indexing\ScipDefinitionEmitter;
use ScipPhp\Indexing\ScipReferenceEmitter;
use ScipPhp\Indexing\TypeResolver;
use ScipPhp\Parser\DocCommentParser;
use ScipPhp\Parser\PosResolver;
use ScipPhp\Types\Types;

use function is_string;
use function ltrim;

final class DocIndexer
{
    private readonly IndexingContext $ctx;

    private readonly TypeResolver $typeResolver;

    private readonly CallRecordBuilder $callBuilder;

    private readonly ExpressionTracker $exprTracker;

    private readonly LocalVariableTracker $localTracker;

    private readonly ScipDefinitionEmitter $defEmitter;

    private readonly ScipReferenceEmitter $refEmitter;

    private readonly DocCommentParser $docCommentParser;

    public function __construct(
        Composer $composer,
        private readonly SymbolNamer $namer,
        private readonly Types $types,
        string $relativePath = '',
        bool $experimental = false,
    ) {
        $this->ctx = new IndexingContext($relativePath, $experimental);
        $this->typeResolver = new TypeResolver($namer, $types);
        $this->callBuilder = new CallRecordBuilder($this->ctx, $this->typeResolver, $namer, $types);
        $this->exprTracker = new ExpressionTracker(
            $this->ctx,
            $this->callBuilder,
            $this->typeResolver,
            $namer,
            $types,
        );
        $this->localTracker = new LocalVariableTracker(
            $this->ctx,
            $this->exprTracker,
            $this->typeResolver,
            $namer,
            $types,
        );
        $this->defEmitter = new ScipDefinitionEmitter(
            $this->ctx,
            $namer,
            $types,
            new DocGenerator(),
        );
        $this->refEmitter = new ScipReferenceEmitter($this->ctx, $namer, $composer);
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
                $this->defEmitter->emitDefinition($pos, $c, $c->name, SyntaxKind::IdentifierConstant);
            }
            return;
        }
        if ($n instanceof ClassLike && $n->name !== null) {
            $this->defEmitter->emitDefinition($pos, $n, $n->name, SyntaxKind::IdentifierType);
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
                $this->defEmitter->emitDocDefinition($n->getDocComment(), '@property', $p, $p->propertyName, $symbol);
            }

            $methods = $this->docCommentParser->parseMethods($n);
            foreach ($methods as $m) {
                if ($m->methodName === '') {
                    continue;
                }
                $symbol = $this->namer->nameMeth($name, $m->methodName);
                $this->defEmitter->emitDocDefinition($n->getDocComment(), '@method', $m, $m->methodName, $symbol);
            }
            return;
        }
        if ($n instanceof ClassMethod) {
            $this->defEmitter->emitDefinition($pos, $n, $n->name);
            return;
        }
        if ($n instanceof EnumCase) {
            $this->defEmitter->emitDefinition($pos, $n, $n->name, SyntaxKind::IdentifierConstant);
            return;
        }
        if ($n instanceof Function_) {
            $this->defEmitter->emitDefinition($pos, $n, $n->name, SyntaxKind::IdentifierFunctionDefinition);
            return;
        }
        if ($n instanceof Param && $n->var instanceof Variable && is_string($n->var->name)) {
            // Constructor property promotion.
            if ($n->flags !== 0) {
                $p = new PropertyItem($n->var->name, $n->default, $n->getAttributes());
                $prop = new Property($n->flags, [$p], $n->getAttributes(), $n->type, $n->attrGroups);
                $p->setAttribute('parent', $prop);
                $this->defEmitter->emitDefinition($pos, $p, $n->var, SyntaxKind::IdentifierParameter);
                // Register promoted property parameter type for resolution
                $this->localTracker->registerParameterType($n);
                // Resolve the promoted Property symbol for the assigned_from edge
                $promotedPropertySymbol = $this->namer->name($p);
                // Create ValueRecord at declaration site (One Value Per Declaration Rule)
                $this->localTracker->createParameterValueRecord($pos, $n, $promotedPropertySymbol);
                return;
            }
            $this->defEmitter->emitDefinition($pos, $n, $n->var, SyntaxKind::IdentifierParameter);
            // Register parameter type for type resolution in expressions
            $this->localTracker->registerParameterType($n);
            // Create ValueRecord at declaration site (One Value Per Declaration Rule)
            $this->localTracker->createParameterValueRecord($pos, $n);
            return;
        }
        if ($n instanceof Property) {
            foreach ($n->props as $p) {
                // Set parent attribute so we can access the type in def()
                $p->setAttribute('parent', $n);
                $this->defEmitter->emitDefinition($pos, $p, $p->name);
            }
            return;
        }

        // ------- Foreach Loop Variables -------

        if ($n instanceof Foreach_ && $n->valueVar instanceof Variable && is_string($n->valueVar->name)) {
            $this->localTracker->handleForeach($pos, $n);
            return;
        }

        // ------- Local Variables -------

        if ($n instanceof Assign && $n->var instanceof Variable && is_string($n->var->name)) {
            $this->localTracker->handleAssignment($pos, $n);
            return;
        }

        // Handle variable reads (not part of assignment LHS)
        if ($n instanceof Variable && is_string($n->name) && $n->name !== 'this') {
            $parent = $n->getAttribute('parent');
            // Skip if this variable is the LHS of an assignment (already handled above)
            if (!($parent instanceof Assign && $parent->var === $n)) {
                $this->localTracker->handleReference($pos, $n);
            }
            return;
        }

        // ------- Usages -------

        if ($n instanceof ClassConstFetch && $n->name instanceof Identifier && $n->name->toString() !== '') {
            $symbol = $this->types->constDef($n->class, $n->name->toString());
            if ($symbol !== null) {
                $this->refEmitter->emitReference($pos, $symbol, $n->name, SyntaxKind::IdentifierConstant);
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
                $this->refEmitter->emitReference($pos, $symbol, $n->name);

                // Call tracking (skip first-class callables like $obj->method(...))
                if (!$n->isFirstClassCallable()) {
                    $callRecord = $this->callBuilder->buildCallRecord(
                        $pos,
                        $n,
                        $symbol,
                        $n->getArgs(),
                        $this->exprTracker->track(...),
                    );
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
                    $callRecord = $this->callBuilder->buildCallRecord(
                        $pos,
                        $n,
                        $symbol,
                        $n->getArgs(),
                        $this->exprTracker->track(...),
                    );
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
                $callRecord = $this->callBuilder->buildCallRecord(
                    $pos,
                    $n,
                    $calleeSymbol,
                    $n->getArgs(),
                    $this->exprTracker->track(...),
                );
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
                $this->refEmitter->emitReference($pos, $symbol, $n);
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
                $this->refEmitter->emitReference($pos, $symbol, $n->name);

                // Call tracking for property access
                // Track receiver (for non-static property access)
                $receiverValueId = null;
                if (!($n instanceof StaticPropertyFetch)) {
                    $receiverValueId = $this->exprTracker->track($pos, $n->var);
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
            $this->exprTracker->track($pos, $n);
            return;
        }
        if ($n instanceof Coalesce) {
            // Track null coalesce: $a ?? $b
            $this->exprTracker->track($pos, $n);
            return;
        }
        if ($n instanceof Ternary) {
            // Track ternary: $a ? $b : $c or $a ?: $b
            $this->exprTracker->track($pos, $n);
            return;
        }
        if ($n instanceof Match_) {
            // Track match expression: match($x) { ... }
            $this->exprTracker->track($pos, $n);
            return;
        }
    }

    /**
     * Reset local variable tracking, values, and calls for a new file.
     */
    public function resetLocals(): void
    {
        $this->ctx->resetLocals();
    }
}
