<?php

declare(strict_types=1);

namespace ScipPhp\Calls;

use JsonSerializable;

/**
 * Represents a value-producing expression tracked for data flow analysis.
 *
 * Values include: parameters, local variables, literals, constants, and call results.
 * These are distinct from calls (method/function invocations, property access, operators).
 */
final readonly class ValueRecord implements JsonSerializable
{
    /**
     * @param  string   $id              Unique identifier: "{file}:{line}:{col}"
     * @param  string   $kind            Value kind: parameter, local, literal, constant, result
     * @param  ?string  $symbol          SCIP symbol, null for literals and results
     * @param  ?string  $type            Type symbol this value has
     * @param  array{file: string, line: int, col: int}  $location  Source location
     * @param  ?string  $sourceCallId    ID of call that produces this value (for locals/results)
     * @param  ?string  $sourceValueId   ID of value this was assigned from (for locals)
     */
    public function __construct(
        public string $id,
        public string $kind,
        public ?string $symbol,
        public ?string $type,
        public array $location,
        public ?string $sourceCallId = null,
        public ?string $sourceValueId = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $result = [
            'id'       => $this->id,
            'kind'     => $this->kind,
            'symbol'   => $this->symbol,
            'type'     => $this->type,
            'location' => $this->location,
        ];

        // Only include source fields when they are set
        if ($this->sourceCallId !== null) {
            $result['source_call_id'] = $this->sourceCallId;
        }
        if ($this->sourceValueId !== null) {
            $result['source_value_id'] = $this->sourceValueId;
        }

        return $result;
    }
}
