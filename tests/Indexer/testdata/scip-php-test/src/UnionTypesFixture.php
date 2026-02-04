<?php

declare(strict_types=1);

namespace TestData;

/**
 * Test fixtures for union and intersection type tracking.
 *
 * Tests that:
 * - Union types produce synthetic type symbols
 * - Methods called on union-typed receivers use synthetic symbols
 * - Return types of union-typed methods are tracked correctly
 * - Nullable types are properly represented as union with null
 */
class UnionTypesFixture
{
    /**
     * Property with union type.
     */
    public string|int $unionProperty;

    /**
     * Property with nullable type (sugar for union with null).
     */
    public ?string $nullableProperty;

    /**
     * Method with union type parameter.
     */
    public function acceptUnion(string|int $value): void
    {
        // Method call on union-typed parameter
        // The callee should be a synthetic union type method
    }

    /**
     * Method with union type return.
     */
    public function returnUnion(): string|int
    {
        return 'result';
    }

    /**
     * Method with nullable return.
     */
    public function returnNullable(): ?string
    {
        return null;
    }

    /**
     * Calling a method on a union-typed receiver.
     */
    public function callOnUnionReceiver(Logger|Auditor $handler): void
    {
        // This should produce a call with callee = scip-php synthetic union . Auditor|Logger#log().
        $handler->log('event');
    }

    /**
     * Chained call on union-typed receiver.
     */
    public function chainedUnionCall(Logger|Auditor $handler): string
    {
        // Chain: $handler->getTag()->process()
        // Each step produces a call record
        return $handler->getTag();
    }

    /**
     * Union with multiple class types.
     * ClassA, ClassB, and ClassC all have a1() or similar methods.
     */
    public function multiClassUnion(ClassA|ClassB $obj): int
    {
        // Method call on union - b2 exists on both ClassA (property) and ClassB (property)
        return $obj->b2;
    }

    /**
     * Coalesce on nullable property produces union type result.
     */
    public function coalesceNullable(): string
    {
        // $this->nullableProperty is ?string = string|null
        // Coalesce removes null: string|null ?? 'default' => string
        return $this->nullableProperty ?? 'default';
    }

    /**
     * Ternary with union-typed branches.
     */
    public function ternaryWithUnions(bool $flag, Logger $a, Auditor $b): Logger|Auditor
    {
        // Result type is Logger|Auditor (union of branch types)
        return $flag ? $a : $b;
    }

    /**
     * Match expression with different return types.
     */
    public function matchWithUnionResult(string $status): Logger|Auditor|null
    {
        return match ($status) {
            'log' => new Logger(),
            'audit' => new Auditor(),
            default => null,
        };
    }

    /**
     * Nested call with union type argument.
     */
    public function nestedCallWithUnion(Logger|Auditor $handler): void
    {
        // The argument's value_type should be the union type symbol
        $this->processHandler($handler);
    }

    private function processHandler(Logger|Auditor $handler): void
    {
        $handler->log('processed');
    }
}

/**
 * Interface for union type testing.
 */
interface Logger
{
    public function log(string $msg): void;
    public function getTag(): string;
}

/**
 * Interface for union type testing.
 */
interface Auditor
{
    public function log(string $msg): void;
    public function getTag(): string;
}
