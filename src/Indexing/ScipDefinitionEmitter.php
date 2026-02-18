<?php

declare(strict_types=1);

namespace ScipPhp\Indexing;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;
use Scip\Occurrence;
use Scip\Relationship;
use Scip\SymbolInformation;
use Scip\SymbolRole;
use Scip\SyntaxKind;
use ScipPhp\DocGenerator;
use ScipPhp\Parser\PosResolver;
use ScipPhp\SymbolNamer;
use ScipPhp\Types\Types;

use function array_merge;
use function array_slice;
use function explode;
use function implode;
use function in_array;
use function is_int;
use function mb_check_encoding;
use function str_starts_with;
use function strtolower;

final class ScipDefinitionEmitter
{
    public function __construct(
        private readonly IndexingContext $ctx,
        private readonly SymbolNamer $namer,
        private readonly Types $types,
        private readonly DocGenerator $docGenerator,
    ) {
    }

    /**
     * Emit a SCIP definition: SymbolInformation + Occurrence with relationships.
     */
    public function emitDefinition(
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
     * Emit a SCIP definition from a doc comment tag (@property, @method).
     *
     * @param  non-empty-string  $tagName
     * @param  non-empty-string  $name
     * @param  non-empty-string  $symbol
     */
    public function emitDocDefinition(
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
    private function extractMethodRelationships(ClassMethod $n, string $methodSymbol): array // phpcs:ignore
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
}
