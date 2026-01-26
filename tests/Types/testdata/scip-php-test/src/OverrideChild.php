<?php

declare(strict_types=1);

namespace TestData;

class OverrideChild extends OverrideMiddle
{
    public function process(): int
    {
        return parent::process() + 1;
    }
}
