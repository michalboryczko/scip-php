<?php

declare(strict_types=1);

namespace ScipPhp\Indexing;

use Closure;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use ScipPhp\Calls\ArgumentRecord;
use ScipPhp\Calls\CallRecord;
use ScipPhp\Calls\ValueRecord;
use ScipPhp\Parser\PosResolver;
use ScipPhp\SymbolNamer;
use ScipPhp\Types\Types;

use function array_search;
use function spl_object_id;

final class CallRecordBuilder
{
    private readonly PrettyPrinter $prettyPrinter;

    public function __construct(
        private readonly IndexingContext $ctx,
        private readonly TypeResolver $typeResolver,
        private readonly SymbolNamer $namer,
        private readonly Types $types,
    ) {
        $this->prettyPrinter = new PrettyPrinter();
    }

    /**
     * Build a call record for a call-site node.
     *
     * @param  PosResolver                              $pos              Position resolver for source locations
     * @param  Node                                     $callNode         The call expression node
     * @param  non-empty-string                         $calleeSymbol     SCIP symbol of the callee
     * @param  list<Arg>                $args             Arguments passed to the call
     * @param  Closure(PosResolver, Expr): ?string $trackExpression  Callback to track sub-expressions
     * @return ?CallRecord   The call record, or null if caller cannot be resolved
     */
    public function buildCallRecord(
        PosResolver $pos,
        Node $callNode,
        string $calleeSymbol,
        array $args,
        Closure $trackExpression,
    ): ?CallRecord {
        $caller = $this->typeResolver->findEnclosingScope($callNode);
        if ($caller === null) {
            return null;
        }

        $posData = $pos->pos($callNode);
        $line = $posData[0] + 1;
        $col = $posData[1];

        $id = $this->ctx->relativePath . ':' . $line . ':' . $col;

        $location = [
            'file' => $this->ctx->relativePath,
            'line' => $line,
            'col'  => $col,
        ];

        $kind = $this->typeResolver->resolveCallKind($callNode);
        $kindType = $this->typeResolver->resolveKindType($kind);

        $returnType = $this->typeResolver->resolveReturnType($callNode, $calleeSymbol);

        $receiverValueId = null;
        if ($callNode instanceof MethodCall || $callNode instanceof NullsafeMethodCall) {
            $receiverValueId = $trackExpression($pos, $callNode->var);
        } elseif ($callNode instanceof StaticCall && $callNode->class instanceof Name) {
            $receiverValueId = null;
        }

        $paramNames = $this->types->getMethodParams($calleeSymbol);

        $arguments = [];
        foreach ($args as $i => $arg) {
            $position = $i;
            $paramName = null;

            if ($arg->name !== null && $paramNames !== null) {
                $argName = $arg->name->toString();
                $nameIndex = array_search($argName, $paramNames, true);
                if ($nameIndex !== false) {
                    $position = $nameIndex;
                    $paramName = $argName;
                }
            } elseif ($paramNames !== null && isset($paramNames[$i])) {
                $paramName = $paramNames[$i];
            }

            $paramSymbol = null;
            if ($paramName !== null && $paramName !== '') {
                $paramSymbol = $this->namer->nameParam($calleeSymbol, $paramName);
            }

            $valueId = $trackExpression($pos, $arg->value);

            $valueExpr = $this->prettyPrinter->prettyPrintExpr($arg->value);
            if ($arg->unpack) {
                $valueExpr = '...' . $valueExpr;
            }

            $arguments[] = new ArgumentRecord(
                position: $position,
                parameter: $paramSymbol,
                valueId: $valueId,
                valueExpr: $valueExpr,
            );
        }

        $record = new CallRecord(
            id: $id,
            kind: $kind,
            kindType: $kindType,
            caller: $caller,
            callee: $calleeSymbol,
            returnType: $returnType,
            receiverValueId: $receiverValueId,
            location: $location,
            arguments: $arguments,
        );

        $this->ctx->expressionIds[spl_object_id($callNode)] = $id;

        return $record;
    }

    /**
     * Add a call record and create a corresponding result value record.
     *
     * @param  CallRecord  $callRecord  The call record to add
     */
    public function addCallWithResultValue(CallRecord $callRecord): void
    {
        $this->ctx->calls[] = $callRecord;

        $resultValue = new ValueRecord(
            id: $callRecord->id,
            kind: 'result',
            symbol: null,
            type: $callRecord->returnType,
            location: $callRecord->location,
            sourceCallId: $callRecord->id,
        );

        $this->ctx->values[] = $resultValue;
    }

    /**
     * Build a call record for access or operator expressions.
     *
     * @param  PosResolver       $pos              Position resolver for source locations
     * @param  Expr              $exprNode         The expression node
     * @param  ?string           $symbol           SCIP symbol being accessed (null for operators)
     * @param  ?string           $receiverValueId  ID of the receiver value/call for chaining
     * @param  ?string           $leftValueId      ID of left operand value (for coalesce)
     * @param  ?string           $rightValueId     ID of right operand value (for coalesce)
     * @param  ?string           $conditionValueId ID of condition value (for ternary)
     * @param  ?string           $trueValueId      ID of true branch value (for ternary_full)
     * @param  ?string           $falseValueId     ID of false branch value (for ternary, ternary_full)
     * @param  ?string           $subjectValueId   ID of match subject value
     * @param  ?list<string>     $armIds           IDs of match arm expressions
     * @param  ?string           $keyValueId       ID of array access key value
     * @param  ?Node             $positionNode     Node to use for ID computation (default: exprNode)
     * @return ?CallRecord       The call record, or null if caller cannot be resolved
     */
    public function buildAccessOrOperatorCallRecord(
        PosResolver $pos,
        Expr $exprNode,
        ?string $symbol,
        ?string $receiverValueId = null,
        ?string $leftValueId = null,
        ?string $rightValueId = null,
        ?string $conditionValueId = null,
        ?string $trueValueId = null,
        ?string $falseValueId = null,
        ?string $subjectValueId = null,
        ?array $armIds = null,
        ?string $keyValueId = null,
        ?Node $positionNode = null,
    ): ?CallRecord {
        $caller = $this->typeResolver->findEnclosingScope($exprNode);
        if ($caller === null) {
            return null;
        }

        $posData = $pos->pos($positionNode ?? $exprNode);
        $line = $posData[0] + 1;
        $col = $posData[1];

        $id = $this->ctx->relativePath . ':' . $line . ':' . $col;

        $location = [
            'file' => $this->ctx->relativePath,
            'line' => $line,
            'col'  => $col,
        ];

        $kind = $this->typeResolver->resolveCallKind($exprNode);
        $kindType = $this->typeResolver->resolveKindType($kind);

        $returnType = $this->typeResolver->resolveAccessReturnType($exprNode, $symbol);

        $callee = $symbol ?? '';
        if ($callee === '' && $kind === 'coalesce') {
            $callee = $this->namer->nameOperator('coalesce') ?? '';
        } elseif ($callee === '' && $kind === 'ternary') {
            $callee = $this->namer->nameOperator('elvis') ?? '';
        } elseif ($callee === '' && $kind === 'ternary_full') {
            $callee = $this->namer->nameOperator('ternary') ?? '';
        } elseif ($callee === '' && $kind === 'match') {
            $callee = $this->namer->nameOperator('match') ?? '';
        }

        $record = new CallRecord(
            id: $id,
            kind: $kind,
            kindType: $kindType,
            caller: $caller,
            callee: $callee,
            returnType: $returnType,
            receiverValueId: $receiverValueId,
            location: $location,
            arguments: [],
            leftValueId: $leftValueId,
            rightValueId: $rightValueId,
            conditionValueId: $conditionValueId,
            trueValueId: $trueValueId,
            falseValueId: $falseValueId,
            subjectValueId: $subjectValueId,
            armIds: $armIds,
            keyValueId: $keyValueId,
        );

        $this->ctx->expressionIds[spl_object_id($exprNode)] = $id;

        return $record;
    }
}
