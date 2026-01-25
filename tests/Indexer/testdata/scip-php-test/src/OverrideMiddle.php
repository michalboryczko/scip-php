<?php

declare(strict_types=1);

namespace TestData;

class OverrideMiddle extends OverrideBase
{
    public function process(): int
    {
        return parent::process() + 1;
    }
}
