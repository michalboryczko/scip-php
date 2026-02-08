<?php

declare(strict_types=1);

namespace ScipPhp\Types\Internal;

use Override;

use function array_filter;
use function array_keys;
use function array_values;
use function count;
use function str_ends_with;

/**
 * Represents a composite type (union or intersection).
 */
final readonly class CompositeType implements Type
{
    /** @var list<non-empty-string> */
    private array $types;

    /**
     * Whether this is an intersection type (true) or a union type (false).
     */
    private bool $isIntersection;

    /** @param  bool  $isIntersection  True for intersection type, false for union type */
    public function __construct(bool $isIntersection = false, ?Type ...$types)
    {
        $flattened = [];
        foreach ($types as $type) {
            if ($type === null) {
                continue;
            }
            foreach ($type->flatten() as $t) {
                $flattened[$t] = true;
            }
        }
        $this->types = array_keys($flattened);
        $this->isIntersection = $isIntersection;
    }

    /**
     * Create a union type from multiple types.
     */
    public static function union(?Type ...$types): self
    {
        return new self(false, ...$types);
    }

    /**
     * Create an intersection type from multiple types.
     */
    public static function intersection(?Type ...$types): self
    {
        return new self(true, ...$types);
    }

    /** @inheritDoc */
    #[Override]
    public function flatten(): array
    {
        return $this->types;
    }

    #[Override]
    public function isComposite(): bool
    {
        return count($this->types) > 1;
    }

    /**
     * Check if this is an intersection type.
     */
    public function isIntersectionType(): bool
    {
        return $this->isIntersection;
    }

    /**
     * Check if this is a union type.
     */
    public function isUnionType(): bool
    {
        return !$this->isIntersection;
    }

    /**
     * Get the number of constituent types.
     */
    public function count(): int
    {
        return count($this->types);
    }

    /**
     * Create a new type with null removed from the union.
     *
     * For coalesce operator: the left side has null removed,
     * because if left is null, the right side is used.
     *
     * @param  ?Type  $type  The type to remove null from
     * @return ?Type  The type with null removed, or null if nothing remains
     */
    public static function removeNull(?Type $type): ?Type
    {
        if ($type === null) {
            return null;
        }

        $flat = $type->flatten();
        $filtered = array_values(array_filter(
            $flat,
            static fn(string $t): bool => !str_ends_with($t, 'null#'),
        ));

        if (count($filtered) === 0) {
            return null;
        }

        if (count($filtered) === 1) {
            return new NamedType($filtered[0]);
        }

        // Rebuild as union
        $types = [];
        foreach ($filtered as $t) {
            $types[] = new NamedType($t);
        }
        return self::union(...$types);
    }
}
