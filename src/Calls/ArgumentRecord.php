<?php

declare(strict_types=1);

namespace ScipPhp\Calls;

use JsonSerializable;

/**
 * Represents a single argument-to-parameter binding at a call site.
 *
 * Note: Type information is available via values[value_id].type lookup.
 */
final readonly class ArgumentRecord implements JsonSerializable
{
    /**
     * @param  int          $position     Zero-based argument index
     * @param  ?string      $parameter    SCIP symbol of the callee's formal parameter, null if unavailable
     * @param  ?string      $valueId      ID of the value or call that produces this argument (unique across values and calls)
     * @param  string       $valueExpr    Pretty-printed source text of the argument expression
     */
    public function __construct(
        public int $position,
        public ?string $parameter,
        public ?string $valueId,
        public string $valueExpr,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'position'   => $this->position,
            'parameter'  => $this->parameter,
            'value_id'   => $this->valueId,
            'value_expr' => $this->valueExpr,
        ];
    }
}
