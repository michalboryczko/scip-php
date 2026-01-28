<?php

declare(strict_types=1);

namespace TestData;

class ClosureCapture
{
    public function run(): void
    {
        $service = new ParameterRefs();
        $fn = function () use ($service) {
            return $service->process([], 0);
        };
    }
}
