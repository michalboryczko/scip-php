<?php

declare(strict_types=1);

namespace ScipPhp\Calls;

use JsonSerializable;

/**
 * Represents a call site (invocation, access, or operator expression) with location and type info.
 *
 * Stable call kinds (always generated):
 * - Invocations: method, method_static, constructor
 * - Access: access (property), access_static
 *
 * Experimental call kinds (require --experimental flag):
 * - Invocations: function
 * - Access: access_array
 * - Operators: coalesce, ternary, ternary_full, match
 *
 * Note: Nullsafe operations ($obj?->prop, $obj?->method()) use regular access/method kinds
 * with a union return_type containing null (e.g., "scip-php synthetic union . null|string#").
 *
 * Values (parameter, local, literal, constant) are tracked separately in ValueRecord.
 */
final readonly class CallRecord implements JsonSerializable
{
    /**
     * @param  string                $id              Unique identifier: "{file}:{line}:{col}"
     * @param  string                $kind            Call kind: method, access, coalesce, etc.
     * @param  string                $kindType        Kind category: invocation, access, operator
     * @param  string                $caller          SCIP symbol of the enclosing method/function
     * @param  string                $callee          SCIP symbol being accessed/called (or operator symbol)
     * @param  ?string               $returnType      Type symbol this expression produces
     * @param  ?string               $receiverValueId ID of the value/call that produces the receiver (for chaining)
     * @param  array{file: string, line: int, col: int}  $location  Source location
     * @param  list<ArgumentRecord>  $arguments       Argument-to-parameter bindings (for invocations)
     * @param  ?string               $leftValueId     ID of left operand value (coalesce)
     * @param  ?string               $rightValueId    ID of right operand value (coalesce)
     * @param  ?string               $conditionValueId ID of condition value (ternary, ternary_full)
     * @param  ?string               $trueValueId     ID of true branch value (ternary_full)
     * @param  ?string               $falseValueId    ID of false branch value (ternary, ternary_full)
     * @param  ?string               $subjectValueId  ID of match subject value
     * @param  ?list<string>         $armIds          IDs of match arm result expressions
     * @param  ?string               $keyValueId      ID of array access key value
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $kindType,
        public string $caller,
        public string $callee,
        public ?string $returnType,
        public ?string $receiverValueId,
        public array $location,
        public array $arguments,
        public ?string $leftValueId = null,
        public ?string $rightValueId = null,
        public ?string $conditionValueId = null,
        public ?string $trueValueId = null,
        public ?string $falseValueId = null,
        public ?string $subjectValueId = null,
        public ?array $armIds = null,
        public ?string $keyValueId = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $result = [
            'id'                => $this->id,
            'kind'              => $this->kind,
            'kind_type'         => $this->kindType,
            'caller'            => $this->caller,
            'callee'            => $this->callee,
            'return_type'       => $this->returnType,
            'receiver_value_id' => $this->receiverValueId,
            'location'          => $this->location,
            'arguments'         => $this->arguments,
        ];

        // Only include operator fields when they are set
        if ($this->leftValueId !== null) {
            $result['left_value_id'] = $this->leftValueId;
        }
        if ($this->rightValueId !== null) {
            $result['right_value_id'] = $this->rightValueId;
        }
        if ($this->conditionValueId !== null) {
            $result['condition_value_id'] = $this->conditionValueId;
        }
        if ($this->trueValueId !== null) {
            $result['true_value_id'] = $this->trueValueId;
        }
        if ($this->falseValueId !== null) {
            $result['false_value_id'] = $this->falseValueId;
        }
        if ($this->subjectValueId !== null) {
            $result['subject_value_id'] = $this->subjectValueId;
        }
        if ($this->armIds !== null) {
            $result['arm_ids'] = $this->armIds;
        }
        // For access_array, always include key_value_id (even if null for array append)
        if ($this->kind === 'access_array') {
            $result['key_value_id'] = $this->keyValueId;
        } elseif ($this->keyValueId !== null) {
            $result['key_value_id'] = $this->keyValueId;
        }

        return $result;
    }
}
