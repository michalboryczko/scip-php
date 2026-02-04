<?php

declare(strict_types=1);

namespace ScipPhp\Types\Internal;

interface Type
{
    /** @return list<non-empty-string> */
    public function flatten(): array;

    /**
     * Returns true if this type is a composite type (union or intersection).
     */
    public function isComposite(): bool;
}
