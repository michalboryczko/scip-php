<?php

declare(strict_types=1);

namespace TestData;

class CallsFuncCaller
{
    public function callFunction(): void
    {
        // Function call (fully qualified for proper resolution)
        $result = \TestData\callsHelperFunction('test');

        // Built-in function call
        $len = strlen('hello');
    }
}
