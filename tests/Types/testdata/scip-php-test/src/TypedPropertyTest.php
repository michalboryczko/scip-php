<?php

declare(strict_types=1);

namespace TestData;

/**
 * Test class for is_type_definition relationships.
 */
class TypedPropertyTest
{
    // Property with class type annotation
    private OverrideBase $baseProperty;

    // Nullable type
    private ?RelationshipTestInterface $interfaceProperty;

    // Constructor with typed parameters
    public function __construct(
        private readonly OverrideBase $promotedProperty,
        RelationshipTestInterface $parameterWithType
    ) {
        $this->interfaceProperty = $parameterWithType;
    }

    // Method with typed return
    public function getBase(): OverrideBase
    {
        return $this->baseProperty;
    }

    // Method with nullable return
    public function getInterface(): ?RelationshipTestInterface
    {
        return $this->interfaceProperty;
    }
}
