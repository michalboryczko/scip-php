<?php

declare(strict_types=1);

namespace TestData;

class ParameterRefs
{
    public function process(array $items, int $count): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[] = $item;
        }
        return array_slice($result, 0, $count);
    }
}
