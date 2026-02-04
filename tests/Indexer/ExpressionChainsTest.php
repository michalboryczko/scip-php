<?php

declare(strict_types=1);

namespace Tests\Indexer;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ScipPhp\Calls\CallRecord;
use ScipPhp\Calls\ValueRecord;
use ScipPhp\Indexer;

use function array_filter;
use function array_merge;
use function array_values;
use function count;
use function in_array;

use const DIRECTORY_SEPARATOR;

/**
 * Tests for expression chain tracking in calls.json.
 *
 * V3 Schema:
 * - Values (parameter, local, literal, constant) are tracked in values array
 * - Calls (invocations, access, operators) are tracked in calls array
 * - receiver_value_id correctly chains expressions
 * - value_id links arguments to their producing values or calls
 * - Operator-specific fields (left_id, right_id, etc.) are populated
 */
final class ExpressionChainsTest extends TestCase
{
    private const string TESTDATA_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'testdata' . DIRECTORY_SEPARATOR;

    /** @var list<ValueRecord> */
    private array $values;

    /** @var list<CallRecord> */
    private array $calls;

    /** @var array<string, ValueRecord> */
    private array $valuesById;

    /** @var array<string, CallRecord> */
    private array $callsById;

    #[RunInSeparateProcess]
    public function testExpressionChainsAreTracked(): void
    {
        $indexer = new Indexer(self::TESTDATA_DIR . 'scip-php-test', 'test', []);
        $indexer->index();
        $this->values = $indexer->getValues();
        $this->calls = $indexer->getCalls();

        $this->valuesById = [];
        foreach ($this->values as $value) {
            $this->valuesById[$value->id] = $value;
        }

        $this->callsById = [];
        foreach ($this->calls as $call) {
            $this->callsById[$call->id] = $call;
        }

        // Verify that expression chain records exist
        self::assertNotEmpty($this->calls, 'Expected call records');
        self::assertNotEmpty($this->values, 'Expected value records');

        // Test specific scenarios
        $this->assertValueRecordsExist();
        $this->assertAccessCallsExist();
        $this->assertCoalesceCallsExist();
        $this->assertTernaryCallsExist();
        $this->assertReceiverValueIdChaining();
        $this->assertValueIdLinking();
        $this->assertDataFlowTracingWorks();
    }

    private function assertValueRecordsExist(): void
    {
        // Check for local variables
        $localValues = $this->findValuesByKind('local');
        self::assertNotEmpty($localValues, 'Expected local value records');

        // Check for parameters
        $parameterValues = $this->findValuesByKind('parameter');
        self::assertNotEmpty($parameterValues, 'Expected parameter value records');

        // Check for literals
        $literalValues = $this->findValuesByKind('literal');
        self::assertNotEmpty($literalValues, 'Expected literal value records');
    }

    private function assertAccessCallsExist(): void
    {
        // In v3, property access uses "access" kind
        $accessCalls = $this->findCallsByKind('access');
        self::assertNotEmpty($accessCalls, 'Expected access call records for property access');

        // Access calls should have a callee (the property symbol)
        foreach ($accessCalls as $call) {
            // Some access calls may not resolve if type is unknown
            // But if they have a callee, it should be a property symbol
            if ($call->callee !== '') {
                self::assertStringContainsString('.', $call->callee, 'Access callee should be a property symbol');
            }
        }
    }

    private function assertCoalesceCallsExist(): void
    {
        $coalesceCalls = $this->findCallsByKind('coalesce');
        self::assertNotEmpty($coalesceCalls, 'Expected coalesce call records');

        // Coalesce calls should have operator symbol and left/right IDs
        foreach ($coalesceCalls as $call) {
            self::assertSame('scip-php operator . coalesce#', $call->callee, 'Coalesce should have operator symbol');
            self::assertNotNull($call->leftId, 'Coalesce should have left_id');
            self::assertNotNull($call->rightId, 'Coalesce should have right_id');
        }
    }

    private function assertTernaryCallsExist(): void
    {
        // Check for elvis (ternary without if) or full ternary
        $ternaryCalls = array_merge(
            $this->findCallsByKind('ternary'),
            $this->findCallsByKind('ternary_full'),
        );
        self::assertNotEmpty($ternaryCalls, 'Expected ternary call records');
    }

    private function assertReceiverValueIdChaining(): void
    {
        // Find access calls that have a receiver_value_id
        $accessCalls = $this->findCallsByKind('access');
        $nullsafeAccessCalls = $this->findCallsByKind('access_nullsafe');
        $allAccessCalls = array_merge($accessCalls, $nullsafeAccessCalls);

        $chainedCalls = array_filter(
            $allAccessCalls,
            static fn(CallRecord $c): bool => $c->receiverValueId !== null,
        );

        self::assertNotEmpty($chainedCalls, 'Expected access calls with receiver_value_id for chaining');

        // For each chained access call, the receiver should exist in values or calls
        foreach ($chainedCalls as $call) {
            $receiverExists = isset($this->valuesById[$call->receiverValueId])
                || isset($this->callsById[$call->receiverValueId]);
            self::assertTrue(
                $receiverExists,
                "receiver_value_id '{$call->receiverValueId}' should reference an existing value or call",
            );
        }
    }

    private function assertValueIdLinking(): void
    {
        // Find method/constructor calls that have arguments
        $methodCalls = array_filter(
            $this->calls,
            static fn(CallRecord $c): bool => in_array($c->kind, ['method', 'constructor'], true)
                && $c->arguments !== [],
        );

        // At least some arguments should have value_id set
        $argumentsWithValueId = 0;
        foreach ($methodCalls as $call) {
            foreach ($call->arguments as $arg) {
                if ($arg->valueId !== null) {
                    $argumentsWithValueId++;

                    // The referenced ID should exist in values or calls
                    $idExists = isset($this->valuesById[$arg->valueId])
                        || isset($this->callsById[$arg->valueId]);
                    self::assertTrue(
                        $idExists,
                        "value_id '{$arg->valueId}' should reference an existing value or call",
                    );
                }
            }
        }

        self::assertGreaterThan(
            0,
            $argumentsWithValueId,
            'Expected at least some arguments to have value_id linking to values or calls',
        );
    }

    /**
     * Verify complete data flow tracing through expression chains.
     *
     * This tests the ability to trace an argument value back to its source
     * by following receiver_value_id and left_id/right_id chains.
     */
    private function assertDataFlowTracingWorks(): void
    {
        // Find a coalesce operator that has left_id and right_id
        $coalesceCalls = $this->findCallsByKind('coalesce');
        $coalesceWithRefs = array_filter(
            $coalesceCalls,
            static fn(CallRecord $c): bool => $c->leftId !== null && $c->rightId !== null,
        );
        self::assertNotEmpty($coalesceWithRefs, 'Expected coalesce with left_id and right_id');

        // For one coalesce, trace the left branch
        $coalesce = array_values($coalesceWithRefs)[0];

        // The left_id should reference an existing value or call
        $leftExists = isset($this->valuesById[$coalesce->leftId])
            || isset($this->callsById[$coalesce->leftId]);
        self::assertTrue($leftExists, 'left_id must reference existing value or call');

        // Trace back through receiver_value_id chain until we hit null (base expression)
        $chain = $this->traceReceiverChain($coalesce->leftId);
        // Chain should have at least the coalesce's left operand
        self::assertNotEmpty($chain, 'Expected non-empty receiver chain for coalesce left operand');

        // Each step in the chain should form a connected sequence
        // The chain is traced backwards (from leaf to root), so current's receiver_value_id
        // should point to the next element in the chain
        for ($i = 0; $i < count($chain) - 1; $i++) {
            $current = $chain[$i];
            $next = $chain[$i + 1];
            // Current's receiver_value_id should be the next call/value's id
            if ($current instanceof CallRecord && $current->receiverValueId !== null) {
                self::assertSame(
                    $next->id,
                    $current->receiverValueId,
                    'Chain must be connected via receiver_value_id',
                );
            }
        }
    }

    /**
     * Trace back through receiver_value_id chain from a given ID.
     *
     * @return list<CallRecord|ValueRecord>
     */
    private function traceReceiverChain(string $startId): array
    {
        $chain = [];
        $currentId = $startId;

        while ($currentId !== null) {
            if (isset($this->callsById[$currentId])) {
                $call = $this->callsById[$currentId];
                $chain[] = $call;
                $currentId = $call->receiverValueId;
            } elseif (isset($this->valuesById[$currentId])) {
                $value = $this->valuesById[$currentId];
                $chain[] = $value;
                // Values don't have receiver chains
                break;
            } else {
                break;
            }
        }

        return $chain;
    }

    /**
     * Find value records with the given kind.
     *
     * @return list<ValueRecord>
     */
    private function findValuesByKind(string $kind): array
    {
        return array_values(
            array_filter($this->values, static fn(ValueRecord $v): bool => $v->kind === $kind),
        );
    }

    /**
     * Find call records with the given kind.
     *
     * @return list<CallRecord>
     */
    private function findCallsByKind(string $kind): array
    {
        return array_values(
            array_filter($this->calls, static fn(CallRecord $c): bool => $c->kind === $kind),
        );
    }
}
