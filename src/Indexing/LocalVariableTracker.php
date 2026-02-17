<?php

declare(strict_types=1);

namespace ScipPhp\Indexing;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use Scip\Occurrence;
use Scip\SymbolInformation;
use Scip\SymbolRole;
use Scip\SyntaxKind;
use ScipPhp\Calls\ValueRecord;
use ScipPhp\Parser\PosResolver;
use ScipPhp\SymbolNamer;
use ScipPhp\Types\Types;

use function is_string;
use function spl_object_id;

final class LocalVariableTracker
{
    public function __construct(
        private readonly IndexingContext $ctx,
        private readonly ExpressionTracker $exprTracker,
        private readonly TypeResolver $typeResolver,
        private readonly SymbolNamer $namer,
        private readonly Types $types,
    ) {
    }

    /**
     * Register a parameter's type in localVars for type resolution.
     *
     * This enables property/method access on parameters to resolve correctly.
     * For example, with `function foo(Message $msg)`, we can resolve `$msg->contact`.
     */
    public function registerParameterType(Param $n): void
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
    public function createParameterValueRecord(
        PosResolver $pos,
        Param $n,
        ?string $promotedPropertySymbol = null,
    ): void {
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
    public function handleAssignment(PosResolver $pos, Assign $n): void
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
        $rhsId = $this->exprTracker->track($pos, $n->expr);

        // Determine if RHS is a call or a value
        if ($rhsId !== null) {
            if ($this->exprTracker->isCallExpression($n->expr)) {
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
     * Handle local variable reference (read).
     */
    public function handleReference(PosResolver $pos, Variable $n): void
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
     * Handle foreach loop variable.
     *
     * For SCIP index: Uses 'local N' format (required by SCIP validator)
     * For calls.json: Uses descriptive format '{scope}local${name}@{line}'
     */
    public function handleForeach(PosResolver $pos, Foreach_ $n): void
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
        $iterableId = $this->exprTracker->track($pos, $n->expr);
        if ($iterableId !== null) {
            if ($this->exprTracker->isCallExpression($n->expr)) {
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
     * Eagerly register foreach loop variables when referenced in loop body.
     *
     * Due to post-order traversal (leaveNode), the loop body is visited BEFORE
     * the Foreach_ node itself. This means variables used inside the loop body
     * are encountered before handleForeach() can register them.
     *
     * This method checks if the variable is defined by an ancestor Foreach_ node
     * and registers it early if needed.
     */
    public function ensureForeachVarRegistered(
        PosResolver $pos,
        Variable $n,
        string $varName,
        string $scope,
    ): void {
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
        $iterableId = $this->exprTracker->track($pos, $foreach->expr);
        if ($iterableId !== null) {
            if ($this->exprTracker->isCallExpression($foreach->expr)) {
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
}
