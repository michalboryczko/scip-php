<?php

declare(strict_types=1);

namespace ScipPhp;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Const_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\UnionType;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;
use Scip\Occurrence;
use Scip\Relationship;
use Scip\SymbolInformation;
use Scip\SymbolRole;
use Scip\SyntaxKind;
use ScipPhp\Composer\Composer;
use ScipPhp\Parser\DocCommentParser;
use ScipPhp\Parser\PosResolver;
use ScipPhp\Types\Types;

use function array_slice;
use function explode;
use function implode;
use function in_array;
use function is_int;
use function is_string;
use function ltrim;
use function str_starts_with;
use function strtolower;

final class DocIndexer
{
    private readonly DocGenerator $docGenerator;

    private readonly DocCommentParser $docCommentParser;

    /** @var array<non-empty-string, SymbolInformation> */
    public array $symbols;

    /** @var array<non-empty-string, SymbolInformation> */
    public array $extSymbols;

    /** @var list<Occurrence> */
    public array $occurrences;

    /** @var int Counter for local variable symbols */
    private int $localCounter = 0;

    /** @var array<string, string> Maps scope+varName to local symbol */
    private array $localSymbols = [];

    public function __construct(
        private readonly Composer $composer,
        private readonly SymbolNamer $namer,
        private readonly Types $types,
    ) {
        $this->docGenerator = new DocGenerator();
        $this->docCommentParser = new DocCommentParser();
        $this->symbols = [];
        $this->extSymbols = [];
        $this->occurrences = [];
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
                return;
            }
            $this->def($pos, $n, $n->var, SyntaxKind::IdentifierParameter);
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
            }
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
        $doc = $this->docGenerator->create($n);
        $this->symbols[$symbol] = new SymbolInformation([
            'symbol'        => $symbol,
            'documentation' => $doc,
        ]);
        $this->occurrences[] = new Occurrence([
            'range'        => $pos->pos($posNode),
            'symbol'       => $symbol,
            'symbol_roles' => SymbolRole::Definition,
            'syntax_kind'  => $kind,
        ]);

        // Add relationships for class-like definitions
        if ($n instanceof ClassLike) {
            $relationships = $this->extractRelationships($n);
            if (!empty($relationships)) {
                $this->symbols[$symbol]->setRelationships($relationships);
            }
        }

        // Add relationships for method overrides
        if ($n instanceof ClassMethod) {
            $relationships = $this->extractMethodRelationships($n, $symbol);
            // Also add type definition relationship for return type
            $returnTypeRelationship = $this->extractReturnTypeRelationship($n);
            if ($returnTypeRelationship !== null) {
                $relationships[] = $returnTypeRelationship;
            }
            if (!empty($relationships)) {
                $this->symbols[$symbol]->setRelationships($relationships);
            }
        }

        // Add type definition relationship for parameters
        if ($n instanceof Param && $n->type !== null) {
            $typeRelationship = $this->extractTypeRelationship($n->type);
            if ($typeRelationship !== null) {
                $this->symbols[$symbol]->setRelationships([$typeRelationship]);
            }
        }

        // Add type definition relationship for properties
        if ($n instanceof PropertyItem) {
            $parent = $n->getAttribute('parent');
            if ($parent instanceof Property && $parent->type !== null) {
                $typeRelationship = $this->extractTypeRelationship($parent->type);
                if ($typeRelationship !== null) {
                    $this->symbols[$symbol]->setRelationships([$typeRelationship]);
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
     * Extract type definition relationship from a return type.
     */
    private function extractReturnTypeRelationship(ClassMethod|Function_ $n): ?Relationship
    {
        if ($n->returnType === null) {
            return null;
        }

        return $this->extractTypeRelationship($n->returnType);
    }

    /**
     * Extract type definition relationship from a type node.
     * Handles Name, NullableType, and UnionType.
     *
     * @param Node $type The type node (Name, NullableType, UnionType, etc.)
     */
    private function extractTypeRelationship(Node $type): ?Relationship
    {
        // Handle nullable types (?Foo)
        if ($type instanceof NullableType) {
            return $this->extractTypeRelationship($type->type);
        }

        // Handle union types (Foo|Bar)
        if ($type instanceof UnionType) {
            // For union types, we'll use the first non-builtin type
            foreach ($type->types as $subType) {
                $relationship = $this->extractTypeRelationship($subType);
                if ($relationship !== null) {
                    return $relationship;
                }
            }
            return null;
        }

        // Handle Name nodes (class/interface references)
        if ($type instanceof Name) {
            // Skip built-in type-like names (null, true, false)
            $name = $type->toString();
            if (in_array(strtolower($name), ['null', 'true', 'false', 'self', 'static', 'parent'], true)) {
                return null;
            }

            $typeSymbol = $this->types->nameDef($type);
            if ($typeSymbol !== null) {
                return new Relationship([
                    'symbol'             => $typeSymbol,
                    'is_type_definition' => true,
                ]);
            }
        }

        return null;
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

        $this->occurrences[] = new Occurrence([
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
        if (!str_starts_with($symbol, 'local ')) {
            $ident = $this->namer->extractIdent($symbol);
            if ($this->composer->isDependency($ident)) {
                $this->extSymbols[$symbol] = new SymbolInformation([
                    'symbol'        => $symbol,
                    'documentation' => [], // TODO(drj): build hover content
                ]);
            }
        }

        $this->occurrences[] = new Occurrence([
            'range'        => $pos->pos($posNode),
            'symbol'       => $symbol,
            'symbol_roles' => $role,
            'syntax_kind'  => $kind,
        ]);
    }

    /**
     * Handle local variable assignment.
     */
    private function handleLocalVariable(PosResolver $pos, Assign $n): void
    {
        if (!($n->var instanceof Variable) || !is_string($n->var->name)) {
            return;
        }

        $varName = $n->var->name;
        $scope = $this->findEnclosingScope($n);

        if ($scope === null) {
            return;
        }

        // Set the scope in Types for proper type tracking
        $this->types->setCurrentScope($scope);

        // Register the variable type
        $type = $this->types->registerLocalVar($varName, $n->expr);

        // Generate local symbol key
        $key = $scope . '::$' . $varName;

        // Check if we already have a symbol for this variable in this scope
        if (!isset($this->localSymbols[$key])) {
            // Create new local symbol
            $localSymbol = 'local ' . $this->localCounter++;
            $this->localSymbols[$key] = $localSymbol;

            // Build documentation for the local variable
            $typeStr = $type !== null ? implode('|', $type->flatten()) : 'mixed';
            $doc = ['```php', '$' . $varName . ': ' . $typeStr, '```'];

            // Emit definition
            $this->symbols[$localSymbol] = new SymbolInformation([
                'symbol'        => $localSymbol,
                'documentation' => $doc,
            ]);

            $this->occurrences[] = new Occurrence([
                'range'        => $pos->pos($n->var),
                'symbol'       => $localSymbol,
                'symbol_roles' => SymbolRole::Definition,
                'syntax_kind'  => SyntaxKind::IdentifierLocal,
            ]);
        } else {
            // Variable already defined - this is a reassignment, emit as reference
            $localSymbol = $this->localSymbols[$key];
            $this->occurrences[] = new Occurrence([
                'range'        => $pos->pos($n->var),
                'symbol'       => $localSymbol,
                'symbol_roles' => SymbolRole::WriteAccess,
                'syntax_kind'  => SyntaxKind::IdentifierLocal,
            ]);
        }
    }

    /**
     * Handle foreach loop variable.
     */
    private function handleForeachVariable(PosResolver $pos, Foreach_ $n): void
    {
        if (!($n->valueVar instanceof Variable) || !is_string($n->valueVar->name)) {
            return;
        }

        $varName = $n->valueVar->name;
        $scope = $this->findEnclosingScope($n);

        if ($scope === null) {
            return;
        }

        // Set the scope in Types for proper type tracking
        $this->types->setCurrentScope($scope);

        // Get the element type from the iterable
        $elementType = $this->types->getForeachElementType($n->expr);

        // Register the variable type in Types
        $this->types->registerLocalVarWithType($varName, $elementType);

        // Generate local symbol key
        $key = $scope . '::$' . $varName;

        // Check if we already have a symbol for this variable in this scope
        if (!isset($this->localSymbols[$key])) {
            // Create new local symbol
            $localSymbol = 'local ' . $this->localCounter++;
            $this->localSymbols[$key] = $localSymbol;

            // Build documentation for the foreach variable
            $typeStr = $elementType !== null ? implode('|', $elementType->flatten()) : 'mixed';
            $doc = ['```php', '$' . $varName . ': ' . $typeStr, '```'];

            // Emit definition
            $this->symbols[$localSymbol] = new SymbolInformation([
                'symbol'        => $localSymbol,
                'documentation' => $doc,
            ]);

            $this->occurrences[] = new Occurrence([
                'range'        => $pos->pos($n->valueVar),
                'symbol'       => $localSymbol,
                'symbol_roles' => SymbolRole::Definition,
                'syntax_kind'  => SyntaxKind::IdentifierLocal,
            ]);
        } else {
            // Variable already defined - this is a reassignment, emit as reference
            $localSymbol = $this->localSymbols[$key];
            $this->occurrences[] = new Occurrence([
                'range'        => $pos->pos($n->valueVar),
                'symbol'       => $localSymbol,
                'symbol_roles' => SymbolRole::WriteAccess,
                'syntax_kind'  => SyntaxKind::IdentifierLocal,
            ]);
        }

        // Also handle key variable if present
        if ($n->keyVar instanceof Variable && is_string($n->keyVar->name)) {
            $keyVarName = $n->keyVar->name;
            $keyKey = $scope . '::$' . $keyVarName;

            if (!isset($this->localSymbols[$keyKey])) {
                $keyLocalSymbol = 'local ' . $this->localCounter++;
                $this->localSymbols[$keyKey] = $keyLocalSymbol;

                // Key type is typically int|string
                $doc = ['```php', '$' . $keyVarName . ': int|string', '```'];

                $this->symbols[$keyLocalSymbol] = new SymbolInformation([
                    'symbol'        => $keyLocalSymbol,
                    'documentation' => $doc,
                ]);

                $this->occurrences[] = new Occurrence([
                    'range'        => $pos->pos($n->keyVar),
                    'symbol'       => $keyLocalSymbol,
                    'symbol_roles' => SymbolRole::Definition,
                    'syntax_kind'  => SyntaxKind::IdentifierLocal,
                ]);
            } else {
                $keyLocalSymbol = $this->localSymbols[$keyKey];
                $this->occurrences[] = new Occurrence([
                    'range'        => $pos->pos($n->keyVar),
                    'symbol'       => $keyLocalSymbol,
                    'symbol_roles' => SymbolRole::WriteAccess,
                    'syntax_kind'  => SyntaxKind::IdentifierLocal,
                ]);
            }
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
        $scope = $this->findEnclosingScope($n);

        if ($scope === null) {
            return;
        }

        // Set the scope in Types for type lookup
        $this->types->setCurrentScope($scope);

        // Check if we have a symbol for this variable
        $key = $scope . '::$' . $varName;

        if (isset($this->localSymbols[$key])) {
            $localSymbol = $this->localSymbols[$key];
            $this->occurrences[] = new Occurrence([
                'range'        => $pos->pos($n),
                'symbol'       => $localSymbol,
                'symbol_roles' => SymbolRole::UnspecifiedSymbolRole,
                'syntax_kind'  => SyntaxKind::IdentifierLocal,
            ]);
        }
    }

    /**
     * Find the enclosing method/function scope for a node.
     * @return ?non-empty-string The scope symbol, or null if not in a scope
     */
    private function findEnclosingScope(Node $n): ?string
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
     * Reset local variable tracking for a new file.
     */
    public function resetLocals(): void
    {
        $this->localCounter = 0;
        $this->localSymbols = [];
    }
}
