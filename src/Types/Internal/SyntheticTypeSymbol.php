<?php

declare(strict_types=1);

namespace ScipPhp\Types\Internal;

use Scip\Relationship;
use Scip\SymbolInformation;
use Scip\SymbolInformation\Kind;
use ScipPhp\SymbolNamer;

use function count;
use function explode;
use function implode;
use function rtrim;
use function sort;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Utility class for creating and managing synthetic type symbols.
 *
 * Synthetic types are union, intersection, and built-in types that have
 * no source-level definition but are represented as first-class symbols
 * in the SCIP index.
 */
final readonly class SyntheticTypeSymbol
{
    public function __construct(private SymbolNamer $namer)
    {
    }

    /**
     * Create a synthetic type symbol from a Type.
     *
     * For NamedType: returns the original symbol or a builtin symbol
     * For CompositeType: returns a union symbol
     *
     * @return ?non-empty-string  The synthetic symbol, or null if not synthetic
     */
    public function fromType(Type $type): ?string
    {
        $flat = $type->flatten();

        // Single type - check if it's a builtin
        if (count($flat) === 1) {
            $name = $flat[0];
            // Check if it's already a builtin symbol
            if (str_starts_with($name, 'scip-php php builtin . ')) {
                return $name;
            }
            // Not a synthetic type - it's a regular named type
            return null;
        }

        // Multiple types - create a union symbol
        return $this->namer->nameUnion($flat);
    }

    /**
     * Create a SymbolInformation entry for a synthetic type.
     *
     * @param  non-empty-string       $symbol  The synthetic type symbol
     * @param  list<non-empty-string> $constituents  The constituent type symbols
     */
    public function createSymbolInfo(string $symbol, array $constituents): SymbolInformation
    {
        $relationships = [];
        foreach ($constituents as $constituentSymbol) {
            $relationships[] = new Relationship([
                'symbol'             => $constituentSymbol,
                'is_type_definition' => true,
            ]);
        }

        return new SymbolInformation([
            'symbol'        => $symbol,
            'kind'          => Kind::TypeAlias,
            'relationships' => $relationships,
        ]);
    }

    /**
     * Create a union type symbol and its SymbolInformation.
     *
     * @param  list<non-empty-string>  $constituentSymbols  Full SCIP symbols of constituent types
     * @return array{symbol: non-empty-string, info: SymbolInformation}
     */
    public function createUnion(array $constituentSymbols): array
    {
        $symbol = $this->namer->nameUnion($constituentSymbols);
        $info = $this->createSymbolInfo($symbol, $constituentSymbols);

        return ['symbol' => $symbol, 'info' => $info];
    }

    /**
     * Create an intersection type symbol and its SymbolInformation.
     *
     * @param  list<non-empty-string>  $constituentSymbols  Full SCIP symbols of constituent types
     * @return array{symbol: non-empty-string, info: SymbolInformation}
     */
    public function createIntersection(array $constituentSymbols): array
    {
        $symbol = $this->namer->nameIntersection($constituentSymbols);
        $info = $this->createSymbolInfo($symbol, $constituentSymbols);

        return ['symbol' => $symbol, 'info' => $info];
    }

    /**
     * Get the symbol for a built-in type, creating it if necessary.
     *
     * @param  string  $typeName  The built-in type name (e.g., 'null', 'string')
     * @return ?non-empty-string  The builtin symbol, or null if not a builtin
     */
    public function getBuiltinSymbol(string $typeName): ?string
    {
        return $this->namer->nameBuiltin($typeName);
    }

    /**
     * Check if a symbol is a synthetic type (union, intersection, or builtin).
     */
    public function isSynthetic(string $symbol): bool
    {
        return str_starts_with($symbol, 'scip-php synthetic union . ')
            || str_starts_with($symbol, 'scip-php synthetic intersection . ')
            || str_starts_with($symbol, 'scip-php php builtin . ');
    }

    /**
     * Check if a symbol is a union type.
     */
    public function isUnion(string $symbol): bool
    {
        return str_starts_with($symbol, 'scip-php synthetic union . ');
    }

    /**
     * Check if a symbol is an intersection type.
     */
    public function isIntersection(string $symbol): bool
    {
        return str_starts_with($symbol, 'scip-php synthetic intersection . ');
    }

    /**
     * Check if a symbol is a built-in type.
     */
    public function isBuiltin(string $symbol): bool
    {
        return str_starts_with($symbol, 'scip-php php builtin . ');
    }

    /**
     * Remove null from a union type (used for ?? operator result type).
     *
     * @param  non-empty-string  $unionSymbol  A union type symbol
     * @return non-empty-string  The union without null, or the original if not a union or no null
     */
    public function removeNullFromUnion(string $unionSymbol): string
    {
        if (!$this->isUnion($unionSymbol)) {
            return $unionSymbol;
        }

        // Parse the union types from the symbol
        // Format: scip-php synthetic union . Foo|Bar|null#
        $prefix = 'scip-php synthetic union . ';
        $typePart = substr($unionSymbol, strlen($prefix));
        $typePart = rtrim($typePart, '#');
        $types = explode('|', $typePart);

        // Filter out null
        $filtered = [];
        foreach ($types as $t) {
            if ($t !== 'null') {
                $filtered[] = $t;
            }
        }

        if (count($filtered) === 0) {
            // Was only null - return mixed
            return 'scip-php php builtin . mixed#';
        }

        if (count($filtered) === 1) {
            // Single type remaining - check if it's a builtin
            $remaining = $filtered[0];
            $builtin = $this->namer->nameBuiltin($remaining);
            if ($builtin !== null) {
                return $builtin;
            }
            // It's a class type - we need to return the full symbol
            // For now, return the short name in union format (will be resolved by caller)
            return $remaining;
        }

        // Multiple types remaining - create new union
        sort($filtered);
        return 'scip-php synthetic union . ' . implode('|', $filtered) . '#';
    }
}
