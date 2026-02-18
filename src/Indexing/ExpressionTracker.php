<?php

declare(strict_types=1);

namespace ScipPhp\Indexing;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
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
use PhpParser\Node\Scalar;
use ScipPhp\Calls\ValueRecord;
use ScipPhp\Parser\PosResolver;
use ScipPhp\SymbolNamer;
use ScipPhp\Types\Types;

use function in_array;
use function is_string;
use function spl_object_id;
use function strtolower;

final class ExpressionTracker
{
    public function __construct(
        private readonly IndexingContext $ctx,
        private readonly CallRecordBuilder $callBuilder,
        private readonly TypeResolver $typeResolver,
        private readonly SymbolNamer $namer,
        private readonly Types $types,
    ) {
    }

    /**
     * Track an expression tree and return the ID for the root expression.
     *
     * This recursively processes an expression, creating records:
     * - ValueRecords for: variables, parameters, literals, constants
     * - CallRecords for: property access, array access, operators, method/function calls
     *
     * @param  PosResolver  $pos   Position resolver
     * @param  Expr         $expr  The expression to track
     * @return ?string      The ID (value or call) for this expression, or null if not trackable
     */
    public function track(PosResolver $pos, Expr $expr): ?string
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
            $expr instanceof Array_ => $this->trackLiteralExpression($pos, $expr),
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
     * Get the ID for an expression, if it has been tracked.
     *
     * @param  Expr    $expr  The expression node
     * @return ?string The ID (value or call), or null if not tracked
     */
    public function getExpressionId(Expr $expr): ?string
    {
        return $this->ctx->expressionIds[spl_object_id($expr)] ?? null;
    }

    /**
     * Check if an expression is a call expression (produces a call record).
     */
    public function isCallExpression(Expr $expr): bool
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
     * Build a value record for value-producing expressions (variables, parameters, literals, constants).
     *
     * In v3, these create ValueRecords instead of CallRecords.
     *
     * @param  PosResolver  $pos            Position resolver for source locations
     * @param  Expr         $exprNode       The expression node
     * @param  string       $kind           Value kind: local, parameter, literal, constant
     * @param  ?string      $symbol         SCIP symbol (null for literals)
     * @param  ?string      $sourceCallId   ID of call this value was assigned from
     * @param  ?string      $sourceValueId  ID of value this was assigned from
     * @return ?ValueRecord The value record, or null if cannot be created
     */
    private function buildValueRecord(
        PosResolver $pos,
        Expr $exprNode,
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
    // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
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
        $receiverValueId = $this->track($pos, $expr->var);

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
        $receiverValueId = $this->track($pos, $expr->var);

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
        $receiverValueId = $this->track($pos, $expr->var);

        // Track the key expression if it exists
        $keyValueId = null;
        if ($expr->dim !== null) {
            $keyValueId = $this->track($pos, $expr->dim);
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
        $leftValueId = $this->track($pos, $expr->left);
        $rightValueId = $this->track($pos, $expr->right);

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
        return $this->track($pos, $expr->left);
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
            $conditionValueId = $this->track($pos, $expr->cond);
            $falseValueId = $this->track($pos, $expr->else);

            $record = $this->callBuilder->buildAccessOrOperatorCallRecord(
                pos: $pos,
                exprNode: $expr,
                symbol: $this->namer->nameOperator('elvis'),
                conditionValueId: $conditionValueId,
                falseValueId: $falseValueId,
            );
        } else {
            // Full ternary: $a ? $b : $c
            $conditionValueId = $this->track($pos, $expr->cond);
            $trueValueId = $this->track($pos, $expr->if);
            $falseValueId = $this->track($pos, $expr->else);

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
        $subjectValueId = $this->track($pos, $expr->cond);

        // Track each arm's result expression
        $armIds = [];
        foreach ($expr->arms as $arm) {
            $armId = $this->track($pos, $arm->body);
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
    private function trackLiteralExpression(PosResolver $pos, Expr $expr): ?string
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
}
