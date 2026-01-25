<?php

declare(strict_types=1);

namespace TestData;

class OverrideBase
{
    public function process(): int
    {
        return 1;
    }

    public function noOverride(): void
    {
    }
}
