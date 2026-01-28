<?php

declare(strict_types=1);

namespace TestData;

class ForeachRefs
{
    public function iterate(array $items): void
    {
        foreach ($items as $key => $value) {
            echo $key;
            echo $value;
        }
    }
}
