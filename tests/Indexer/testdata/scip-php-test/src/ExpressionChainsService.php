<?php

declare(strict_types=1);

namespace TestData;

/**
 * Test fixtures for expression chain tracking in calls.json.
 *
 * This file exercises the full expression chain tracking feature,
 * including variable accesses, property chains, operators, and literals.
 */
class ExpressionChainsService
{
    /**
     * Simple variable access as argument.
     */
    public function variableAsArgument(string $name): void
    {
        // $name is tracked as a variable call, then passed as argument
        $this->logName($name);
    }

    /**
     * Property chain as argument.
     */
    public function propertyChainAsArgument(Message $msg): void
    {
        // $msg->address->street is a chain:
        // 1. $msg (variable)
        // 2. ->address (property)
        // 3. ->street (property)
        $this->logName($msg->address->street ?? 'unknown');
    }

    /**
     * Nullsafe property chain with coalesce.
     */
    public function nullsafeChainWithCoalesce(Message $msg): ?Coordinates
    {
        // Full chain: $msg->address->coordinates?->latitude ?? 0.0
        return new Coordinates(
            $msg->address?->coordinates?->latitude ?? 0.0,
            $msg->address?->coordinates?->longitude ?? 0.0
        );
    }

    /**
     * Ternary expression as argument.
     */
    public function ternaryAsArgument(bool $flag, string $a, string $b): void
    {
        // Full ternary: $flag ? $a : $b
        $this->logName($flag ? $a : $b);
    }

    /**
     * Elvis operator as argument.
     */
    public function elvisAsArgument(?string $name): void
    {
        // Elvis: $name ?: 'default'
        $this->logName($name ?: 'default');
    }

    /**
     * Match expression as argument.
     */
    public function matchAsArgument(string $status): void
    {
        // Match expression
        $label = match($status) {
            'active' => 'Active Status',
            'pending' => 'Pending Status',
            default => 'Unknown Status',
        };
        $this->logName($label);
    }

    /**
     * Nested constructor calls.
     */
    public function nestedConstructors(): Message
    {
        // Nested: new Message(new Address(..., new Coordinates(...)))
        return new Message(
            new Address(
                'Main Street',
                new Coordinates(51.5074, -0.1278)
            )
        );
    }

    /**
     * Array access as argument.
     */
    public function arrayAccessAsArgument(array $data): void
    {
        // Array access: $data['name']
        $this->logName($data['name'] ?? 'unknown');
    }

    /**
     * Class constant as argument.
     */
    public function constantAsArgument(): void
    {
        $this->logPrecision(self::DEFAULT_PRECISION);
    }

    /**
     * Literal values as arguments.
     */
    public function literalsAsArguments(): void
    {
        $this->logName('literal string');
        $this->logPrecision(42);
        $this->logFlag(true);
    }

    /**
     * Complex chained expression.
     * $msg->address->coordinates?->latitude ?? $fallback->coordinates->latitude ?? 0.0
     */
    public function complexChain(Message $msg, Address $fallback): float
    {
        return $msg->address?->coordinates?->latitude
            ?? $fallback->coordinates?->latitude
            ?? 0.0;
    }

    private const DEFAULT_PRECISION = 6;

    private function logName(?string $name): void
    {
        // Just a sink for arguments
    }

    private function logPrecision(int $precision): void
    {
        // Just a sink for arguments
    }

    private function logFlag(bool $flag): void
    {
        // Just a sink for arguments
    }
}
