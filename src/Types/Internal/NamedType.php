<?php

declare(strict_types=1);

namespace ScipPhp\Types\Internal;

use Override;

final readonly class NamedType implements Type
{
    /** @param  non-empty-string  $name */
    public function __construct(private string $name)
    {
    }

    /** @inheritDoc */
    #[Override]
    public function flatten(): array
    {
        return [$this->name];
    }

    #[Override]
    public function isComposite(): bool
    {
        return false;
    }

    /**
     * Get the symbol name.
     *
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }
}
