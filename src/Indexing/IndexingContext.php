<?php

declare(strict_types=1);

namespace ScipPhp\Indexing;

use Scip\Occurrence;
use Scip\SymbolInformation;
use ScipPhp\Calls\CallRecord;
use ScipPhp\Calls\ValueRecord;

use function in_array;

final class IndexingContext
{
    /**
     * Call kinds that are experimental and require --experimental flag.
     * These are incomplete or work-in-progress features.
     */
    private const EXPERIMENTAL_KINDS = [
        'function',
        'access_array',
        'coalesce',
        'ternary',
        'ternary_full',
        'match',
    ];

    /** @var array<non-empty-string, SymbolInformation> */
    public array $symbols = [];

    /** @var array<non-empty-string, SymbolInformation> */
    public array $extSymbols = [];

    /** @var list<Occurrence> */
    public array $occurrences = [];

    /** @var list<ValueRecord> */
    public array $values = [];

    /** @var list<CallRecord> */
    public array $calls = [];

    /** @var array<non-empty-string, SymbolInformation> */
    public array $syntheticTypeSymbols = [];

    /** @var int Counter for local variable symbols */
    public int $localCounter = 0;

    /** @var array<string, string> scope+varName -> SCIP symbol */
    public array $localSymbols = [];

    /** @var array<string, int> scope+varName -> assignment line */
    public array $localAssignmentLines = [];

    /** @var array<string, string> scope+varName -> calls.json symbol */
    public array $localCallsSymbols = [];

    /** @var array<int, string> expression node ID -> value/call ID */
    public array $expressionIds = [];

    /** @var array<string, string> local symbol -> ValueRecord ID */
    public array $localValueIds = [];

    /** @var array<string, string> parameter symbol -> ValueRecord ID */
    public array $parameterValueIds = [];

    public function __construct(
        public readonly string $relativePath,
        public readonly bool $experimental,
    ) {
    }

    /**
     * Check if a call kind is experimental.
     *
     * @param  string  $kind  The call kind to check
     * @return bool  True if the kind is experimental
     */
    public function isExperimentalKind(string $kind): bool
    {
        return in_array($kind, self::EXPERIMENTAL_KINDS, true);
    }

    /** Reset per-file tracking state (called between files). */
    public function resetLocals(): void
    {
        $this->localCounter = 0;
        $this->localSymbols = [];
        $this->localCallsSymbols = [];
        $this->localAssignmentLines = [];
        $this->values = [];
        $this->calls = [];
        $this->expressionIds = [];
        $this->localValueIds = [];
        $this->parameterValueIds = [];
    }
}
